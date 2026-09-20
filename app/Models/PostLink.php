<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The card shown for the first link in a post's body.
 *
 * Its own table rather than columns on posts, because fetching somebody
 * else's page is allowed to fail - slow host, no network, a page with no
 * Open Graph tags - and a post with no preview row is simply a post without
 * a card, not a broken one.
 */
class PostLink extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['post_id', 'url', 'title', 'description', 'image_url', 'site_name'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** The bit of the address worth showing: the host, without the www. */
    public function host(): string
    {
        $host = parse_url($this->url, PHP_URL_HOST) ?: $this->url;

        return preg_replace('/^www\./', '', $host);
    }

    /** A card with nothing but a host is not worth drawing. */
    public function isUseful(): bool
    {
        return filled($this->title) || filled($this->description) || filled($this->image_url);
    }
}
