<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\User;
use App\Support\Mentions;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function store(Request $request, Post $post)
    {
        // Only people who can see a post can react to or comment on it: a
        // group post is for its members, and a post waiting for approval is
        // for its author and the people running the group.
        abort_unless($post->isOpenTo($request->user()), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        $this->notifyForComment($comment, $post, $request->user());

        if ($request->expectsJson()) {
            $comment->setRelation('user', $request->user());
            $comment->setRelation('replies', collect());
            $comment->setRelation('likes', collect());

            return response()->json([
                'success' => true,
                'html' => view('feed._comment', ['comment' => $comment])->render(),
            ]);
        }

        return back();
    }

    /**
     * Sends the notifications a new comment triggers. Each recipient gets only the most specific one
     * that applies: mention, reply to their comment, comment on their post, then also-commented.
     */
    protected function notifyForComment(Comment $comment, Post $post, User $author): void
    {
        $served = [(int) $author->id];

        // 1. Mentions. sync() returns only the newly stored ones.
        foreach (Mentions::sync($comment, $comment->body, (int) $author->id) as $mentioned) {
            Notifier::send($mentioned->id, $author, Notifier::MENTION, 'comment', $comment->id, 'mentioned you in a comment.');
            $served[] = (int) $mentioned->id;
        }

        // 2. Reply to a comment.
        $parentAuthorId = null;
        if ($comment->parent_id) {
            $parentAuthorId = (int) (Comment::where('id', $comment->parent_id)->value('user_id') ?? 0);

            if ($parentAuthorId && ! in_array($parentAuthorId, $served, true)) {
                Notifier::send($parentAuthorId, $author, Notifier::COMMENT_REPLY, 'comment', $comment->parent_id, 'replied to your comment.');
                $served[] = $parentAuthorId;
            }
        }

        // 3. The post's author.
        $postAuthorId = (int) $post->user_id;
        if (! in_array($postAuthorId, $served, true)) {
            Notifier::send(
                $postAuthorId,
                $author,
                Notifier::POST_COMMENT,
                'post',
                $post->id,
                'commented on your post.',
                Comment::where('post_id', $post->id)
                    ->where('is_deleted', false)
                    ->where('user_id', '!=', $postAuthorId)
                    ->distinct()
                    ->count('user_id'),
            );
            $served[] = $postAuthorId;
        }

        // 4. Everyone else already in the thread, capped.
        $participants = Comment::where('post_id', $post->id)
            ->where('is_deleted', false)
            ->whereNotIn('user_id', $served)
            ->distinct()
            ->limit(Notifier::ALSO_COMMENTED_LIMIT)
            ->pluck('user_id');

        Notifier::sendMany($participants, $author, Notifier::ALSO_COMMENTED, 'post', $post->id, 'also commented on a post you commented on.');
    }

    public function update(Request $request, Comment $comment)
    {
        Gate::authorize('update', $comment);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $comment->update(['body' => $data['body']]);

        // Only newly added mentions come back from sync(), so nobody is notified twice.
        foreach (Mentions::sync($comment, $comment->body, (int) $comment->user_id) as $mentioned) {
            Notifier::send($mentioned->id, $request->user(), Notifier::MENTION, 'comment', $comment->id, 'mentioned you in a comment.');
        }

        return back();
    }

    public function destroy(Request $request, Comment $comment)
    {
        $user = $request->user();
        if (! $user->isAdmin() && $user->id !== $comment->user_id) {
            abort(403);
        }

        $comment->update([
            'is_deleted' => true,
            'deleted_reason' => $user->isAdmin() ? 'Removed by administrator.' : null,
        ]);

        return back();
    }

    /** Applies, switches, or clears a reaction on a comment. Choosing the current reaction clears it. */
    public function react(Request $request, Comment $comment)
    {
        abort_unless($comment->post && $comment->post->isOpenTo($request->user()), 404);

        $data = $request->validate([
            'reaction' => ['nullable', Rule::in(array_keys(ReactionController::REACTIONS))],
        ]);

        $reaction = $data['reaction'] ?? 'like';
        $userId = $request->user()->id;

        $query = CommentLike::where('comment_id', $comment->id)->where('user_id', $userId);
        $existing = $query->first();
        $active = true;

        if ($existing && $existing->reaction === $reaction) {
            $query->delete();
            $active = false;
            $reaction = null;
        } elseif ($existing) {
            $query->update(['reaction' => $reaction]);
        } else {
            CommentLike::create([
                'comment_id' => $comment->id,
                'user_id' => $userId,
                'reaction' => $reaction,
            ]);
        }

        // Notify only while a reaction stands.
        if ($active) {
            Notifier::send(
                $comment->user_id,
                $request->user(),
                Notifier::COMMENT_REACTION,
                'comment',
                $comment->id,
                'reacted to your comment.',
                CommentLike::where('comment_id', $comment->id)->where('user_id', '!=', $comment->user_id)->count(),
            );
        }

        return $this->respond($request, [
            'active' => $active,
            'reaction' => $reaction,
            'count' => $comment->likes()->count(),
        ]);
    }

    public function report(Request $request, Comment $comment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        CommentReport::updateOrCreate([
            'comment_id' => $comment->id,
            'reporter_id' => $request->user()->id,
            'status' => 'pending',
        ], $data);

        return back()->with('success', 'Report sent to moderators.');
    }
}
