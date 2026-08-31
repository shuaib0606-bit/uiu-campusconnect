<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\PostEditHistory;
use App\Models\PostMedia;
use App\Models\PostSave;
use App\Models\PostShare;
use App\Models\HiddenPost;
use App\Models\Report;
use App\Support\Mentions;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    /** Attachments allowed on one post. */
    public const MAX_MEDIA = 10;

    /** Per-file upload ceiling, in kilobytes. */
    public const MAX_FILE_KB = 225280;

    /** Post body ceiling, in characters. */
    public const MAX_BODY = 5000;

    public function store(Request $request)
    {
        // A single "media" file is normalised into the array shape the rules below expect.
        if ($request->hasFile('media') && ! is_array($request->file('media'))) {
            $request->files->set('media', [$request->file('media')]);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.self::MAX_BODY],
            'visibility' => ['nullable', 'string', 'in:uiu,department,connections'],
            'media' => ['nullable', 'array', 'max:'.self::MAX_MEDIA],
            'media.*' => ['file', 'max:'.self::MAX_FILE_KB, 'mimetypes:image/*,video/*'],
            'poll_options' => ['nullable', 'array'],
            'poll_options.*' => ['nullable', 'string', 'max:255'],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
        ]);

        $uploads = $this->collectUploads($request);

        // The media_type and media_path columns mirror the first attachment.
        $mediaType = $uploads[0]['type'] ?? 'none';
        $mediaPath = $uploads[0]['path'] ?? null;

        $clubId = null;
        if (! empty($data['club_id'])) {
            $club = \App\Models\Club::find($data['club_id']);
            if ($club && \App\Services\SocialRules::canPostClubEvent($request->user(), $club)) {
                $clubId = $club->id;
            }
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'club_id' => $clubId,
            'body' => $data['body'],
            'visibility' => $data['visibility'] ?? 'uiu',
            'media_type' => $mediaType,
            'media_path' => $mediaPath,
            'post_type' => filled(array_filter($data['poll_options'] ?? [])) ? 'poll' : 'standard',
        ]);

        foreach ($uploads as $i => $upload) {
            PostMedia::create([
                'post_id' => $post->id,
                'path' => $upload['path'],
                'type' => $upload['type'],
                'mime_type' => $upload['mime'],
                'size' => $upload['size'],
                // Files arrive in the order the author arranged them.
                'position' => $i,
            ]);
        }

        foreach (array_values(array_filter($data['poll_options'] ?? [])) as $i => $label) {
            PollOption::create(['post_id' => $post->id, 'label' => $label, 'position' => $i]);
        }

        $this->syncMentions($post, $request->user(), 'mentioned you in a post.');

        return back()->with('success', 'Post published.');
    }

    /**
     * Moves uploaded files onto the public disk. The mime type is sniffed before store() moves the
     * temporary file away.
     *
     * @return list<array{path: string, type: string, mime: string, size: int}>
     */
    protected function collectUploads(Request $request): array
    {
        $files = $request->file('media');

        if ($files === null) {
            return [];
        }

        $files = is_array($files) ? array_values(array_filter($files)) : [$files];
        $files = array_slice($files, 0, self::MAX_MEDIA);

        $uploads = [];

        foreach ($files as $file) {
            $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
            $size = (int) $file->getSize();

            $uploads[] = [
                'path' => $file->store('posts', 'public'),
                'type' => str_starts_with($mime, 'video') ? 'video' : 'image',
                'mime' => $mime,
                'size' => $size,
            ];
        }

        return $uploads;
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

        // sync() returns only newly added mentions, so nobody is notified twice.
        $this->syncMentions($post, $request->user(), 'mentioned you in a post.');

        return back()->with('success', 'Post updated.');
    }

    /** Stores the mentions found in a post body and notifies newly named users. */
    protected function syncMentions(Post $post, $actor, string $message): void
    {
        foreach (Mentions::sync($post, $post->body, (int) $post->user_id) as $mentioned) {
            Notifier::send($mentioned->id, $actor, Notifier::MENTION, 'post', $post->id, $message);
        }
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
