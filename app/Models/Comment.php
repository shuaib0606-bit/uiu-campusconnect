<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Comment extends Model
{
    protected $fillable = ['post_id', 'user_id', 'parent_id', 'body', 'is_deleted', 'deleted_reason'];

    protected function casts(): array
    {
        return ['is_deleted' => 'boolean'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    /** Raw @-mention rows, written by App\Support\Mentions::sync(). */
    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }

    /** The users named in this comment's body. */
    public function mentionedUsers(): MorphToMany
    {
        return $this->morphToMany(User::class, 'mentionable', 'mentions');
    }
}
