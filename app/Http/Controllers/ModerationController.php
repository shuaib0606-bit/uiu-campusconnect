<?php

namespace App\Http\Controllers;

use App\Models\CommentReport;
use App\Models\ProfileReport;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ModerationController extends Controller
{
    public const OPEN_STATUSES = ['open', 'pending', 'reviewing'];

    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $tab = in_array($request->get('tab'), ['open', 'posts', 'comments', 'profiles', 'resolved'], true) ? $request->get('tab') : 'open';

        $posts = Report::with(['reporter', 'post.user'])->latest()->limit(100)->get();
        $comments = CommentReport::with(['reporter', 'comment.user'])->latest()->limit(100)->get();
        $profiles = ProfileReport::with(['reporter', 'reportedUser'])->latest()->limit(100)->get();

        $counts = [
            'posts' => (clone $posts)->whereIn('status', self::OPEN_STATUSES)->count(),
            'comments' => (clone $comments)->whereIn('status', self::OPEN_STATUSES)->count(),
            'profiles' => (clone $profiles)->whereIn('status', self::OPEN_STATUSES)->count(),
        ];

        if ($tab === 'posts') {
            $comments = collect();
            $profiles = collect();
        } elseif ($tab === 'comments') {
            $posts = collect();
            $profiles = collect();
        } elseif ($tab === 'profiles') {
            $posts = collect();
            $comments = collect();
        }

        if ($tab === 'resolved') {
            $posts = $posts->whereNotIn('status', self::OPEN_STATUSES);
            $comments = $comments->whereNotIn('status', self::OPEN_STATUSES);
            $profiles = $profiles->whereNotIn('status', self::OPEN_STATUSES);
        } elseif ($tab === 'open') {
            $posts = $posts->whereIn('status', self::OPEN_STATUSES);
            $comments = $comments->whereIn('status', self::OPEN_STATUSES);
            $profiles = $profiles->whereIn('status', self::OPEN_STATUSES);
        }
        // Single-type tabs show every status for that type.

        return Inertia::render('Admin/Moderation', [
            'posts' => $posts->values()->map(fn (Report $r) => $this->card(
                $r,
                route('moderation.posts.review', $r),
                'Remove post',
                $r->post
                    ? 'By '.$r->post->user->full_name.': '.Str::limit($r->post->body, 220)
                    : null,
                'This post no longer exists.',
            ))->values(),

            'comments' => $comments->values()->map(fn (CommentReport $r) => $this->card(
                $r,
                route('moderation.comments.review', $r),
                'Remove comment',
                $r->comment
                    ? 'By '.($r->comment->user->full_name ?? 'Unknown').': '.Str::limit($r->comment->body, 220)
                    : null,
                'This comment no longer exists.',
            ))->values(),

            'profiles' => $profiles->values()->map(fn (ProfileReport $r) => $this->card(
                $r,
                route('moderation.profiles.review', $r),
                'Suspend user',
                // The reporter's own description is sent as 'details'.
                null,
                null,
                $r->reportedUser ? [
                    'name' => $r->reportedUser->full_name,
                    'url' => route('profile.show', $r->reportedUser),
                ] : null,
            ))->values(),

            'counts' => $counts,
            'tab' => $tab,
        ]);
    }

    /**
     * One report in the shape the React page draws. Every type shares it, so
     * the page has a single card component instead of three near-copies.
     */
    private function card($report, string $reviewUrl, string $actionLabel, ?string $quote, ?string $missing, ?array $against = null): array
    {
        $open = in_array($report->status, self::OPEN_STATUSES, true);

        return [
            'id' => $report->id,
            'status' => $report->status,
            'status_label' => ucfirst($report->status),
            'is_open' => $open,
            'created_at' => $report->created_at->format('M j, Y g:i A'),
            'reporter' => $report->reporter?->full_name ?? 'Unknown',
            'reason' => $report->reason,
            'details' => $report->details ?? null,
            'quote' => $quote,
            'missing' => $quote === null ? $missing : null,
            'against' => $against,
            'moderator_note' => $report->moderator_note,
            'review_url' => $reviewUrl,
            'action_label' => $actionLabel,
        ];
    }

    public function reviewPost(Request $request, Report $report)
    {
        return $this->reviewReport($request, $report, fn () => $report->post?->update(['is_deleted' => true, 'deleted_reason' => 'Removed by moderator.']));
    }

    public function reviewComment(Request $request, CommentReport $commentReport)
    {
        return $this->reviewReport($request, $commentReport, fn () => $commentReport->comment?->delete());
    }

    public function reviewProfile(Request $request, ProfileReport $profileReport)
    {
        return $this->reviewReport($request, $profileReport, function () use ($profileReport, $request) {
            $note = trim((string) $request->get('moderator_note', ''));

            // forceFill, not update: the three signup_review_* columns are not
            // mass assignable, so update() suspended the account and threw the
            // reason away, leaving "Not reviewed" on the approvals screen.
            $profileReport->reportedUser?->forceFill([
                'status' => 'suspended',
                'signup_review_note' => $note !== '' ? $note : 'Suspended after profile report.',
                'signup_reviewed_by' => $request->user()->id,
                'signup_reviewed_at' => now(),
            ])->save();
        });
    }

    private function reviewReport(Request $request, $report, \Closure $onAction)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'decision' => ['required', 'in:action,dismiss'],
            'moderator_note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['decision'] === 'action') {
            $onAction();
        }

        $report->update([
            'status' => $data['decision'] === 'action' ? 'resolved' : 'dismissed',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'moderator_note' => $data['moderator_note'] ?? null,
        ]);

        return back()->with('success', $data['decision'] === 'action' ? 'Report actioned.' : 'Report dismissed.');
    }
}
