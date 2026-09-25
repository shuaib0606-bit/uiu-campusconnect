<?php

namespace App\Support;

use App\Models\User;

/**
 * Who may see one piece of a profile.
 *
 *   everyone  anyone who can open the profile page
 *   members   UIU members: signed-in accounts that are active. Today every
 *             profile needs a sign-in, so this matches "everyone"; it is
 *             kept separate so a public profile page, if one is ever added,
 *             cannot show members-only details to strangers.
 *   only_me   the owner, and nobody else - not administrators either
 *
 * Used by the profile page, the AI assistant and search alike, so a detail
 * hidden in one place is hidden in all of them.
 */
final class Visibility
{
    public const EVERYONE = 'everyone';

    public const MEMBERS = 'members';

    public const ONLY_ME = 'only_me';

    public const ALL = [self::EVERYONE, self::MEMBERS, self::ONLY_ME];

    public const LABELS = [
        self::EVERYONE => 'Everyone',
        self::MEMBERS => 'UIU members',
        self::ONLY_ME => 'Only me',
    ];

    public static function allows(?string $level, ?User $viewer, User|int $owner): bool
    {
        $ownerId = $owner instanceof User ? $owner->id : $owner;

        return match ($level ?: self::EVERYONE) {
            self::ONLY_ME => $viewer !== null && (int) $viewer->id === (int) $ownerId,
            self::MEMBERS => $viewer !== null && ($viewer->status === 'active' || (int) $viewer->id === (int) $ownerId),
            default => true,
        };
    }

    /** The levels a signed-in, active viewer who is not the owner can see. */
    public static function forMembers(): array
    {
        return [self::EVERYONE, self::MEMBERS];
    }

    public static function valid(?string $level): string
    {
        return in_array($level, self::ALL, true) ? $level : self::EVERYONE;
    }
}
