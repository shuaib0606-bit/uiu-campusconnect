<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Club;
use App\Models\ClubPanelChange;
use App\Models\ClubRoleRequest;
use App\Models\ConnectionApplication;
use App\Models\Notice;
use App\Models\Post;
use App\Policies\CommentPolicy;
use App\Policies\PostPolicy;
use App\Services\SocialRules;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Admins can do anything a policy would otherwise block.
        Gate::before(function ($user, string $ability) {
            return $user->isAdmin() ? true : null;
        });

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinutes(15, 8)->by($email.'|'.$request->ip());
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer('components.app-layout', function ($view) {
            if (Auth::check()) {
                $user = Auth::user();
                $view->with('unreadMessages', SocialRules::unreadMessageCount($user));
                $view->with('unreadNotifications', SocialRules::unreadNotificationCount($user));

                $pendingApplications = ConnectionApplication::whereIn('status', ['pending', 'reviewing'])
                    ->when(! $user->isAdmin(), function ($query) use ($user) {
                        $query->where(fn ($q) => $q->where('student_id', $user->id)->orWhere('target_id', $user->id));
                    })
                    ->count();

                $pendingApplications += ClubRoleRequest::where('status', ClubRoleRequest::PENDING)
                    ->where(fn ($q) => $q
                        ->where('nominated_by', $user->id)
                        ->orWhereIn('club_id', Club::where('moderator_id', $user->id)->pluck('id')))
                    ->count();

                $pendingApplications += ClubPanelChange::where('status', ClubPanelChange::PENDING)
                    ->where(fn ($q) => $q
                        ->where('proposed_by', $user->id)
                        ->orWhereIn('club_id', Club::where('moderator_id', $user->id)->pluck('id')))
                    ->count();

                $view->with('pendingApplications', $pendingApplications);

                // Course and club announcements this person has not opened.
                $view->with('unreadGroupNotices', Notice::unreadGroupCountFor($user));
            }
        });
    }
}
