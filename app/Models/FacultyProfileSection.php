<?php

namespace App\Models;

use App\Support\Visibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One heading of a faculty member's academic profile: Education,
 * Publications, a section they added themselves. The body is Markdown.
 *
 * A section that came from the department website keeps updating from it
 * until its owner edits it (edited_at) or deletes it (removed_at); after
 * that the website never overwrites or restores it. website_body always
 * holds the website's latest text so the owner can go back to it.
 */
class FacultyProfileSection extends Model
{
    public const WEBSITE = 'website';

    public const CUSTOM = 'custom';

    protected $fillable = [
        'user_id', 'title', 'body', 'position', 'visibility',
        'source', 'source_key', 'website_body', 'edited_at', 'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Sections this viewer may see on this owner's profile. */
    public function scopeVisibleTo(Builder $query, ?User $viewer, int $ownerId): Builder
    {
        if ($viewer && (int) $viewer->id === $ownerId) {
            return $query;
        }

        return $query->whereIn('visibility', $viewer && $viewer->status === 'active'
            ? Visibility::forMembers()
            : [Visibility::EVERYONE]);
    }

    public function isFromWebsite(): bool
    {
        return $this->source === self::WEBSITE;
    }

    /** Edited here, so the website sync no longer touches it. */
    public function isLocked(): bool
    {
        return $this->isFromWebsite() && $this->edited_at !== null;
    }

    /** A short, URL-safe name for the Academic tab's menu. */
    public function slug(): string
    {
        return Str::slug($this->title) ?: 'section-'.$this->id;
    }

    /**
     * The body as HTML. Raw HTML in the source is stripped and unsafe links
     * (javascript:, data:) are dropped, so text copied from another website
     * or typed into the editor can never become script on this one.
     */
    public function html(): string
    {
        return self::render($this->body);
    }

    public static function render(?string $markdown): string
    {
        return Str::markdown((string) $markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** Plain text, for places that show a short excerpt. */
    public function plain(int $limit = 300): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($this->html())))), $limit);
    }

    /** How many entries a list section holds ("Publications (142)"), when that is worth showing. */
    public function entryCount(): ?int
    {
        $html = $this->html();
        $items = substr_count($html, '<li>');

        // Publication lists are often numbered paragraphs ("B.39. ...") rather than list items.
        if ($items < 5) {
            $items = preg_match_all('/<p>\s*(<strong>)?\s*\[?[A-Z]{0,3}\.?\s?\d+[.)\]]/', $html);
        }

        return $items >= 5 ? $items : null;
    }
}
