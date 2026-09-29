<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Mentions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** People lookup for the composer's mention autocomplete. Returns a handful of active users and only the fields the picker draws. */
class UserSearchController extends Controller
{
    /** How many suggestions the picker shows at once. */
    public const LIMIT = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        $viewer = $request->user();
        $term = trim((string) ($data['q'] ?? ''));

        $query = User::query()
            ->where('status', 'active')
            // Self-mentions are never stored, so the viewer is not suggested.
            ->whereKeyNot($viewer->id)
            // A block hides each side from the other.
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('user_blocks')
                ->where(fn ($w) => $w
                    ->where(fn ($x) => $x
                        ->whereColumn('user_blocks.blocked_id', 'users.id')
                        ->where('user_blocks.blocker_id', $viewer->id))
                    ->orWhere(fn ($x) => $x
                        ->whereColumn('user_blocks.blocker_id', 'users.id')
                        ->where('user_blocks.blocked_id', $viewer->id))));

        // A student is only offered faculty, officials and admins they are
        // connected with - the same rule Mentions::sync() enforces on save.
        if ($viewer->role === 'student') {
            $connected = \App\Support\GroupInvites::connectedIds($viewer);
            $query->where(fn ($q) => $q
                ->where('role', 'student')
                ->orWhereIn('id', $connected ?: [0]));
        }

        if ($term !== '') {
            // Matched against the display name and the three sources User::handle() falls back through.
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $prefix = str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(fn ($q) => $q
                ->where('full_name', 'like', $like)
                ->orWhere('student_id', 'like', $prefix)
                ->orWhere('employee_id', 'like', $prefix)
                ->orWhere('email', 'like', $prefix));

            // Prefix matches sort first.
            $query->orderByRaw('CASE WHEN full_name LIKE ? THEN 0 ELSE 1 END', [$prefix]);
        }

        $users = $query
            ->orderBy('full_name')
            // Over-fetched because the handle filter below drops some rows.
            ->limit(self::LIMIT * 3)
            ->get(['id', 'full_name', 'avatar_initials', 'role', 'student_id', 'employee_id', 'email']);

        $results = $users
            ->map(fn (User $user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                // Mentions::tokenFor() builds the only token form the parser resolves.
                'handle' => Mentions::tokenFor($user),
                'avatar_initials' => $user->avatar_initials,
                'avatar_url' => $user->avatar_url,
                'role' => $user->role,
                'role_label' => $user->roleLabel(),
            ])
            ->filter(fn (array $row) => $row['handle'] !== '')
            ->unique('handle')
            ->take(self::LIMIT)
            ->values();

        return response()->json(['results' => $results]);
    }
}
