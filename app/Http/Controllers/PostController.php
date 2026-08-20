<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\PostEditHistory;
use App\Models\PostSave;
use App\Models\PostShare;
use App\Models\HiddenPost;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['nullable', 'string', 'in:uiu,department,connections'],
            'media' => ['nullable', 'file', 'max:225280'],
            'poll_options' => ['nullable', 'array'],
            'poll_options.*' => ['nullable', 'string', 'max:255'],
        ]);

        $mediaType = 'none';
        $mediaPath = null;
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mediaPath = $file->store('posts', 'public');
            $mediaType = str_starts_with((string) $file->getClientMimeType(), 'video') ? 'video' : 'image';
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'visibility' => $data['visibility'] ?? 'uiu',
            'media_type' => $mediaType,
            'media_path' => $mediaPath,
            'post_type' => filled(array_filter($data['poll_options'] ?? [])) ? 'poll' : 'standard',
        ]);

        foreach (array_values(array_filter($data['poll_options'] ?? [])) as $i => $label) {
            PollOption::create(['post_id' => $post->id, 'label' => $label, 'position' => $i]);
        }

        return back()->with('success', 'Post published.');
    }

    public function show(Post $post)
    {
        $post->load(['user.profile', 'reactions', 'comments.user.profile', 'comments.replies.user.profile', 'pollOptions.votes']);

        return view('feed.show', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        Gate::authorize('update', $post);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        PostEditHistory::create(['post_id' => $post->id, 'previous_body' => $post->body, 'edited_at' => now()]);
        $post->update(['body' => $data['body']]);

        return back()->with('success', 'Post updated.');
    }

    public function destroy(Request $request, Post $post)
    {
        $user = $request->user();
        if (! $user->isAdmin() && $user->id !== $post->user_id) {
            abort(403);
        }

        $reason = $request->input('reason', $user->isAdmin() ? 'Removed by administrator.' : 'Deleted by author.');
        $post->update(['is_deleted' => true, 'deleted_reason' => $reason]);

        if ($user->isAdmin()) {
            \App\Models\AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'deleted_post',
                'entity_type' => 'post',
                'entity_id' => $post->id,
                'details' => $reason,
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'Post removed.');
    }

    public function toggleSave(Request $request, Post $post)
    {
        $userId = $request->user()->id;
        $query = PostSave::where('post_id', $post->id)->where('user_id', $userId);
        $active = true;

        if ($query->exists()) {
            $query->delete();
            $active = false;
        } else {
            PostSave::create(['post_id' => $post->id, 'user_id' => $userId]);
        }

        return $this->respond($request, ['active' => $active], $active ? 'Saved.' : 'Removed from saved.');
    }

    public function hide(Request $request, Post $post)
    {
        HiddenPost::firstOrCreate(['post_id' => $post->id, 'user_id' => $request->user()->id]);

        return $this->respond($request, ['active' => true], 'You will see less of this.');
    }

    public function share(Request $request, Post $post)
    {
        PostShare::create(['post_id' => $post->id, 'user_id' => $request->user()->id, 'created_at' => now()]);

        return $this->respond($request, ['active' => true], 'Shared to your profile.');
    }

    public function report(Request $request, Post $post)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        Report::updateOrCreate([
            'reporter_id' => $request->user()->id,
            'post_id' => $post->id,
            'status' => 'open',
        ], $data);

        return back()->with('success', 'Report sent to moderators.');
    }

    public function vote(Request $request, Post $post)
    {
        $data = $request->validate(['option_id' => ['required', 'integer']]);
        $option = PollOption::where('post_id', $post->id)->findOrFail($data['option_id']);

        PollVote::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $request->user()->id],
            ['poll_option_id' => $option->id]
        );

        return back();
    }
}
