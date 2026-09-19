<?php

namespace App\Support;

use App\Models\Club;
use App\Models\ClubPanelChange;
use Illuminate\Support\Facades\DB;

/**
 * Applies a panel handover.
 *
 * Kept out of the controller because it is the one operation in the club that
 * has to be all-or-nothing: every old designation goes, every new one lands,
 * and the club group is told - or none of it happens. Half a handover leaves a
 * club with two presidents and nobody able to say which is real.
 */
class ClubPanel
{
    /** Designations that are not a designation. */
    public const PLAIN = ['member', 'general member', ''];

    /**
     * The people currently holding a designation, as
     * [user_id => designation].
     *
     * @return array<int, string>
     */
    public static function current(Club $club): array
    {
        return DB::table('club_members')
            ->where('club_id', $club->id)
            ->whereNotNull('club_role')
            ->whereNotIn(DB::raw('LOWER(club_role)'), self::PLAIN)
            // The moderator is staff, appointed by a super admin. A student
            // panel handover must not touch them.
            ->when($club->moderator_id, fn ($q) => $q->where('user_id', '!=', $club->moderator_id))
            ->pluck('club_role', 'user_id')
            ->all();
    }

    /**
     * Swaps the whole panel over. Returns the panel that was replaced, so the
     * change record can keep it.
     *
     * @return array<int, array{user_id: int, club_role: string}>
     */
    public static function apply(ClubPanelChange $change): array
    {
        $club = $change->club;

        return DB::transaction(function () use ($change, $club) {
            $previous = [];

            foreach (self::current($club) as $userId => $role) {
                $previous[] = ['user_id' => (int) $userId, 'club_role' => $role];
            }

            // Everybody loses their title first. The outgoing panel stays in
            // the club as ordinary members - only the moderator can actually
            // remove somebody - but they stop being officers today.
            DB::table('club_members')
                ->where('club_id', $club->id)
                ->when($club->moderator_id, fn ($q) => $q->where('user_id', '!=', $club->moderator_id))
                ->update(['club_role' => 'member', 'can_post_events' => false]);

            foreach ($change->panelMembers as $seat) {
                // Somebody put on the panel who had drifted out of the club is
                // put back in, rather than the row silently doing nothing.
                DB::table('club_members')->updateOrInsert(
                    ['club_id' => $club->id, 'user_id' => $seat->user_id],
                    [
                        'club_role' => $seat->club_role,
                        'can_post_events' => true,
                        'joined_at' => now(),
                    ]
                );
            }

            // The club group mirrors club_members, so it has to be told.
            GroupSync::club($club->fresh());

            return $previous;
        });
    }

    /**
     * Why this slate cannot be accepted, or null when it can.
     *
     * @param  array<int, array{user_id: int, club_role: string}>  $seats
     */
    public static function problemWith(array $seats): ?string
    {
        if ($seats === []) {
            return 'Add at least one panel member.';
        }

        $presidents = 0;

        foreach ($seats as $seat) {
            if (strtolower(trim($seat['club_role'])) === ClubPanelChange::PRESIDENT) {
                $presidents++;
            }
        }

        if ($presidents === 0) {
            // Without one, nobody can propose the next handover except the
            // moderator, and the club loses its student voice.
            return 'One of the panel has to be the President.';
        }

        if ($presidents > 1) {
            return 'A panel can only have one President.';
        }

        return null;
    }
}
