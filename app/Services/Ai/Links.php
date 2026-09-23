<?php

namespace App\Services\Ai;

use App\Models\Club;
use App\Models\CommunityGroup;
use App\Models\Event;
use App\Models\LostFoundItem;
use App\Models\MarketplaceItem;
use App\Models\Notice;
use App\Models\Post;
use App\Models\User;

/**
 * Links to the pages the assistant talks about.
 *
 * Every link is a path on this site ("/profile/k3v9x0qz7m"), never a full URL, so
 * the chat page can tell at a glance that a link is internal and render
 * it as one - and anything that does not start with a single "/" is shown
 * as plain text. A link grants nothing: the page behind it applies its
 * own access rules exactly as it would for a click from anywhere else.
 *
 * People are addressed by their random public id, never the numeric
 * database id, so no link the assistant prints can be counted up or down
 * to reach somebody else.
 *
 * The links also carry identity across turns. Only the text of earlier
 * answers is sent back to the model, so "send me her profile" after a
 * marketplace answer can only find the right Nusrat if that answer linked
 * the seller. people(profile: "/profile/k3v9x0qz7m") then looks up exactly that
 * person instead of every Nusrat at the university.
 */
class Links
{
    public static function profile(User|int|null $user): ?string
    {
        return $user ? route('profile.show', $user, false) : null;
    }

    /**
     * The public id in a profile link ("/profile/k3v9x0qz7m").
     *
     * Only the random public id is accepted - a bare number is refused -
     * so the lookup cannot be used to step through accounts by counting.
     */
    public static function profileKey(string $link): ?string
    {
        if (preg_match('~(?:^|/)profile/([a-z0-9]{6,16})(?:[/?#].*)?$~', trim($link), $m) && ! ctype_digit($m[1])) {
            return $m[1];
        }

        return null;
    }

    public static function club(?Club $club): ?string
    {
        return $club?->slug ? route('clubs.show', $club, false) : null;
    }

    public static function event(?Event $event): ?string
    {
        return $event ? route('events.show', $event, false) : null;
    }

    public static function notice(?Notice $notice): ?string
    {
        return $notice ? route('notices.show', $notice, false) : null;
    }

    public static function group(?CommunityGroup $group): ?string
    {
        return $group?->slug ? route('groups.show', $group, false) : null;
    }

    public static function chat(?CommunityGroup $group): ?string
    {
        return $group ? route('groups.chat.index', $group, false) : null;
    }

    /** The viewer's own conversation with this person. */
    public static function messages(?User $user): ?string
    {
        return $user ? route('messages.index', ['with' => $user->public_id], false) : null;
    }

    public static function post(?Post $post): ?string
    {
        return $post ? route('posts.show', $post, false) : null;
    }

    public static function marketplace(?MarketplaceItem $item): ?string
    {
        return $item ? route('marketplace.show', $item, false) : null;
    }

    public static function lostFound(?LostFoundItem $item): ?string
    {
        return $item ? route('lost-found.show', $item, false) : null;
    }

    /** A person as the model should see them in a list: name and link. */
    public static function person(?User $user, array $extra = []): ?array
    {
        return $user ? ['name' => $user->full_name, 'link' => static::profile($user)] + $extra : null;
    }
}
