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
use App\Support\LinkPreview;
use App\Support\Mentions;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /** Attachments allowed on one post. */
    public const MAX_MEDIA = 10;

    /**
     * What may be attached. Pictures and video are shown inline; everything
     * else is offered as a download. Deliberately a list of extensions rather
     * than "any file": an upload box that takes anything is an upload box
     * somebody will put an executable in.
     */
    public const ALLOWED_EXTENSIONS = 'jpg,jpeg,png,gif,webp,heic,mp4,mov,webm,avi,'
        .'pdf,doc,docx,ppt,pptx,xls,xlsx,csv,txt,md,zip';

    /** Languages the code composer offers. Anything else is stored as text. */
    public const CODE_LANGUAGES = [
        'text', 'c', 'cpp', 'java', 'python', 'javascript', 'typescript',
        'php', 'sql', 'html', 'css', 'bash', 'json',
    ];

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
            'media.*' => ['file', 'max:'.self::MAX_FILE_KB, 'mimes:'.self::ALLOWED_EXTENSIONS],
            'code_language' => ['nullable', 'string', 'max:30'],
            'poll_options' => ['nullable', 'array'],
            'poll_options.*' => ['nullable', 'string', 'max:255'],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'group_id' => ['nullable', 'integer', 'exists:community_groups,id'],
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

        // A group post belongs to that group's wall and nowhere else, so it is
        // never also club-scoped, and its audience is the membership rather
        // than one of the three feed audiences.
        $groupId = null;
        if (! empty($data['group_id'])) {
            $group = \App\Models\CommunityGroup::find($data['group_id']);

            if ($group && $group->members()->where('users.id', $request->user()->id)->exists()) {
                $groupId = $group->id;
                $clubId = null;
            }
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'club_id' => $clubId,
            'group_id' => $groupId,
            'body' => $data['body'],
            'visibility' => $groupId ? 'uiu' : ($data['visibility'] ?? 'uiu'),
            'media_type' => $mediaType,
            'media_path' => $mediaPath,
            'post_type' => $this->postType($data),
            'code_language' => $this->codeLanguage($data),
        ]);

        foreach ($uploads as $i => $upload) {
            PostMedia::create([
                'post_id' => $post->id,
                'path' => $upload['path'],
                'disk' => $upload['disk'],
                'original_name' => $upload['original'],
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

        // Best-effort and quiet: a slow or unreachable site means no card,
        // never a failed post. Code posts are skipped, because a URL inside
        // a snippet is part of the code and not something to preview.
        if (! $post->isCode()) {
            LinkPreview::attach($post);
        }

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
            $type = static::mediaTypeFor($mime);

            // Pictures and video have to be publicly readable, or <img> and
            // <video> cannot load them. A document does not, and a document
            // on a connections-only post being downloadable by anybody with
            // the address would make the audience setting a lie.
            $disk = $type === PostMedia::FILE ? PostMedia::PRIVATE_DISK : 'public';

            $uploads[] = [
                'path' => $file->store('posts', $disk),
                'disk' => $disk,
                'original' => $file->getClientOriginalName(),
                'type' => $type,
                'mime' => $mime,
                'size' => $size,
            ];
        }

        return $uploads;
    }

    /**
     * Which kind of post this is. Code wins over a poll, because a snippet
     * with poll options attached is a mistake rather than both at once.
     *
     * @param  array<string, mixed>  $data
     */
    private function postType(array $data): string
    {
        if (filled($data['code_language'] ?? null)) {
            return 'code';
        }

        return filled(array_filter($data['poll_options'] ?? [])) ? 'poll' : 'standard';
    }

    /** @param  array<string, mixed>  $data */
    private function codeLanguage(array $data): ?string
    {
        $language = trim((string) ($data['code_language'] ?? ''));

        if ($language === '') {
            return null;
        }

        return in_array(strtolower($language), self::CODE_LANGUAGES, true)
            ? strtolower($language)
            : 'text';
    }

    /** image, video, or a document. */
    protected static function mediaTypeFor(string $mime): string
    {
        if (str_starts_with($mime, 'image')) {
            return PostMedia::IMAGE;
        }

        if (str_starts_with($mime, 'video')) {
            return PostMedia::VIDEO;
        }

        return PostMedia::FILE;
    }

    /**
     * The only way to read a document attached to a post. The parent post is
     * re-fetched through the viewer's own filter, so a document on a hidden,
     * blocked or limited-audience post is not reachable by address.
     */
    public function downloadMedia(Request $request, PostMedia $media)
    {
        abort_unless($media->isFile(), 404);

        $allowed = Post::visible()
            ->forViewer($request->user())
            ->whereKey($media->post_id)
            ->exists();

        // A group post is excluded by scopeVisible, so membership of the
        // group is the other way in.
        if (! $allowed) {
            $post = Post::find($media->post_id);

            $allowed = $post
                && $post->group
                && $post->group->members()->where('users.id', $request->user()->id)->exists();
        }

        abort_unless($allowed, 403, 'You cannot open this attachment.');

        $disk = $media->disk ?: PostMedia::PRIVATE_DISK;

        abort_unless(Storage::disk($disk)->exists($media->path), 404, 'That file is no longer on the server.');

        return Storage::disk($disk)->download($media->path, $media->original_name ?: basename($media->path));
    }

    public function show(Request $request, Post $post)
    {
        // A group post lives on its group's wall. Members are sent there
        // rather than shown a 404; everybody else falls through and gets one,
        // because scopeVisible below excludes group posts entirely.
        if ($post->group_id && $post->group
            && $post->group->members()->where('users.id', $request->user()->id)->exists()) {
            return redirect()->route('groups.show', $post->group);
        }

        // Fetched again through the viewer's filter, so a hidden, blocked or
        // limited-audience post cannot be opened by guessing the address.
        $post = Post::visible()
            ->forViewer($request->user())
            ->whereKey($post->getKey())
            ->firstOrFail();

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
