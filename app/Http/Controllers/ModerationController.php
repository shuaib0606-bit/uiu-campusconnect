<?php

namespace App\Http\Controllers;

use App\Models\CommentReport;
use App\Models\ProfileReport;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;

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

        return view('admin.moderation', [
            'posts' => $posts->values(),
            'comments' => $comments->values(),
            'profiles' => $profiles->values(),
            'counts' => $counts,
            'tab' => $tab,
        ]);
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
            $profileReport->reportedUser?->update(['status' => 'suspended', 'signup_review_note' => $note !== '' ? $note : 'Suspended after profile report.', 'signup_reviewed_by' => $request->user()->id, 'signup_reviewed_at' => now()]);
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
