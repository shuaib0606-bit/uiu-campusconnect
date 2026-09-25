<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name', 'email', 'password', 'role', 'authority_level',
        'department', 'designation', 'student_id', 'employee_id', 'year',
        'status', 'avatar_initials', 'is_verified', 'must_change_password',
        'password_changed_at', 'last_login_at', 'student_id_card_path',
        'signup_requested_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Almost every page draws at least one avatar, so the profile row is
     * always loaded. Without this each avatar costs its own query.
     */
    protected $with = ['profile'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'signup_requested_at' => 'datetime',
            'signup_reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->public_id ??= static::newPublicId();
        });
    }

    /**
     * Ten random characters that stand for this person in addresses -
     * /profile/k3v9x0qz7m rather than /profile/237. A sequential id lets
     * anyone step through every account by counting; this does not. The
     * numeric id is still the key everywhere inside the database.
     */
    public static function newPublicId(): string
    {
        do {
            $id = Str::lower(Str::random(10));
            // At least one letter, so a public id can never be mistaken
            // for (or pass as) a numeric database id.
        } while (ctype_digit($id) || static::query()->where('public_id', $id)->exists());

        return $id;
    }

    /**
     * Always has a value, even for a row that was inserted without the
     * model (a raw insert skips the creating hook) or loaded with a column
     * list that left it out. Filled at most once, and never overwritten.
     */
    protected function publicId(): Attribute
    {
        return Attribute::get(function ($value) {
            if ($value || ! $this->exists) {
                return $value;
            }

            DB::table('users')->where('id', $this->id)->whereNull('public_id')
                ->update(['public_id' => static::newPublicId()]);

            return $this->attributes['public_id'] = DB::table('users')->where('id', $this->id)->value('public_id');
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin' && $this->status === 'active';
    }

    public function getFilamentName(): string
    {
        return $this->full_name ?? $this->email;
    }

    // Relationships

    /** The academic profile copied from the department website, in page order. */
    public function facultySections(): HasMany
    {
        return $this->hasMany(FacultyProfileSection::class)->whereNull('removed_at')->orderBy('position');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'requester_id');
    }

    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'addressee_id');
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'followed_id')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'followed_id', 'follower_id')->withTimestamps();
    }

    public function clubs(): BelongsToMany
    {
        // club_members has joined_at instead of created_at/updated_at, so the
        // pivot columns are listed explicitly.
        return $this->belongsToMany(Club::class, 'club_members')->withPivot(['club_role', 'can_post_events', 'joined_at']);
    }

    public function moderatedClubs(): HasMany
    {
        return $this->hasMany(Club::class, 'moderator_id');
    }

    /** Both pivots carry created_at only, so withTimestamps() cannot be used. */
    public function registeredEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_registrations')->withPivot('created_at');
    }

    public function interestedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_interests')->withPivot('created_at');
    }

    public function signupReviewer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'signup_reviewed_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->latest('created_at');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    // Role helpers

    /** Full URL of the profile photo, or null when the person has not set one. */
    public function getAvatarUrlAttribute(): ?string
    {
        $path = $this->profile?->profile_photo_path;

        return $path ? Storage::url($path) : null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isFaculty(): bool
    {
        return $this->role === 'faculty';
    }

    public function isOfficial(): bool
    {
        return $this->role === 'official';
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Developer Admin',
            'official' => 'University Official',
            'faculty' => 'Faculty',
            default => 'Student',
        };
    }

    public function handle(): string
    {
        return '@'.Str::lower($this->student_id ?? $this->employee_id ?? Str::before($this->email, '@'));
    }

    public function canIssueNotices(): bool
    {
        return in_array($this->role, ['official', 'admin'], true)
            && in_array($this->authority_level, ['registrar', 'developer'], true);
    }

    public function canIssueSectionAnnouncements(): bool
    {
        return $this->canIssueNotices();
    }

    /** Authority levels that may publish a university-wide event. */
    public const EVENT_AUTHORITY = [
        'vc', 'pro_vc', 'dean', 'head', 'director', 'program_coordinator',
    ];

    public function canPostUniversityEvent(): bool
    {
        return $this->role === 'admin'
            || ($this->role === 'faculty' && in_array($this->authority_level, self::EVENT_AUTHORITY, true));
    }

    /** Human label for an authority level, used on profiles and the directory. */
    public function authorityLabel(): ?string
    {
        return [
            'vc' => 'Vice Chancellor',
            'pro_vc' => 'Pro Vice Chancellor',
            'dean' => 'Dean',
            'head' => 'Head of Department',
            'director' => 'Director',
            'program_coordinator' => 'Program Coordinator',
            'registrar' => 'Registrar',
            'club_moderator' => 'Club Moderator',
        ][$this->authority_level] ?? null;
    }
}
