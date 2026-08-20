<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name', 'email', 'password', 'role', 'authority_level',
        'department', 'designation', 'student_id', 'employee_id', 'year',
        'status', 'avatar_initials', 'is_verified', 'must_change_password',
        'password_changed_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
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
        return $this->belongsToMany(Club::class, 'club_members')->withPivot(['club_role', 'can_post_events'])->withTimestamps(false);
    }

    public function moderatedClubs(): HasMany
    {
        return $this->hasMany(Club::class, 'moderator_id');
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

    public function canIssueNotices(): bool
    {
        return in_array($this->role, ['official', 'admin'], true)
            && in_array($this->authority_level, ['registrar', 'developer'], true);
    }

    public function canIssueSectionAnnouncements(): bool
    {
        return $this->canIssueNotices();
    }

    public function canPostUniversityEvent(): bool
    {
        return $this->role === 'admin'
            || ($this->role === 'faculty' && in_array($this->authority_level, ['vc', 'pro_vc', 'dean'], true));
    }
}
