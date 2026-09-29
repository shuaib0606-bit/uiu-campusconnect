<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Models\ClubPanelChange;
use App\Models\ClubRoleRequest;
use App\Models\ConnectionApplication;
use App\Models\Notice;
use App\Services\SocialRules;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props every React page receives without asking for them.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'public_id' => $user->public_id,
                    'full_name' => $user->full_name,
                    'handle' => $user->handle(),
                    'role' => $user->role,
                    'authority_label' => $user->authorityLabel(),
                    'department' => $user->department,
                    'avatar_url' => $user->avatar_url,
                    'avatar_initials' => $user->avatar_initials,
                    'role_label' => $user->roleLabel(),
                    'is_admin' => $user->isAdmin(),
                    'can_issue_section_announcements' => $user->canIssueSectionAnnouncements(),
                ] : null,
            ],

            // The "UIU Official Sites" dialog in the sidebar.
            'uiuSites' => fn () => $user ? \App\Support\UiuSites::all($user) : [],

            // The sidebar badge counts, matching the Blade view composer.
            'badges' => $user ? [
                'messages' => fn () => SocialRules::unreadMessageCount($user),
                'notifications' => fn () => SocialRules::unreadNotificationCount($user),
                // Course and club announcements this person has not opened.
                'groups' => fn () => Notice::unreadGroupCountFor($user),

                'applications' => fn () => ConnectionApplication::whereIn('status', ['pending', 'reviewing'])
                    ->when(! $user->isAdmin(), fn ($q) => $q->where(
                        fn ($inner) => $inner->where('student_id', $user->id)->orWhere('target_id', $user->id)
                    ))
                    ->count()
                    + ClubRoleRequest::where('status', ClubRoleRequest::PENDING)
                        ->where(fn ($q) => $q
                            ->where('nominated_by', $user->id)
                            ->orWhereIn('club_id', Club::where('moderator_id', $user->id)->pluck('id')))
                        ->count()
                    + ClubPanelChange::where('status', ClubPanelChange::PENDING)
                        ->where(fn ($q) => $q
                            ->where('proposed_by', $user->id)
                            ->orWhereIn('club_id', Club::where('moderator_id', $user->id)->pluck('id')))
                        ->count()
                    // Group invitations waiting for this person's answer.
                    + \App\Models\GroupInvite::where('user_id', $user->id)
                        ->where('status', \App\Models\GroupInvite::PENDING)
                        ->count(),
            ] : [],

            // Closures are only evaluated when the page is actually rendered.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
            ],

            'theme' => $request->cookie(config('app.theme_cookie_name', 'cc_theme')) === 'dark'
                ? 'dark'
                : 'light',
        ];
    }
}
