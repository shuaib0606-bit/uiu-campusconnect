<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\CommentReport;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(Request $request, Post $post)
    {
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

    public function update(Request $request, Comment $comment)
    {
        Gate::authorize('update', $comment);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $comment->update(['body' => $data['body']]);

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

    public function like(Request $request, Comment $comment)
    {
        $userId = $request->user()->id;
        $query = CommentLike::where('comment_id', $comment->id)->where('user_id', $userId);
        $active = true;

        if ($query->exists()) {
            $query->delete();
            $active = false;
        } else {
            CommentLike::create(['comment_id' => $comment->id, 'user_id' => $userId]);
        }

        return $this->respond($request, [
            'active' => $active,
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
