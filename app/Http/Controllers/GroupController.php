<?php

namespace App\Http\Controllers;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupFile;
use App\Models\CourseEnrollment;
use App\Models\Notice;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GroupController extends Controller
{
    /**
     * Group files live on the private disk. On the public disk they sat under
     * /storage/group-files and anybody holding the address could download
     * them, signed in or not, which is not what "class resources" should mean.
     */
    public const FILE_DISK = 'local';

    public const FILE_FOLDER = 'group-files';

    public function index(Request $request)
    {
        $viewer = $request->user();
        $q = trim((string) $request->get('q', ''));
        $type = trim((string) $request->get('type', ''));

        // What a student actually opens this page for: their classes this
        // trimester, then their clubs, then everything else. The search box
        // only applies to the open directory at the bottom, because you do
        // not search for a class you are already in.
        $mine = $viewer
            ? CommunityGroup::query()
                ->whereIn('kind', [CommunityGroup::KIND_COURSE, CommunityGroup::KIND_CLUB])
                ->whereExists(fn ($sub) => $sub->selectRaw('1')
                    ->from('community_group_members')
                    ->whereColumn('community_group_members.group_id', 'community_groups.id')
                    ->where('community_group_members.user_id', $viewer->id)
                    ->where('community_group_members.role', '!=', CommunityGroup::PENDING))
                ->with(['section.trimester'])
                ->withCount(['members', 'posts'])
                ->orderByRaw("FIELD(kind, '".CommunityGroup::KIND_COURSE."') DESC")
                ->orderBy('name')
                ->get()
            : collect();

        $groups = CommunityGroup::query()
            ->where('kind', CommunityGroup::KIND_OPEN)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('department', 'like', "%{$q}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('group_type', $type))
            ->withCount(['members', 'files', 'posts'])
            ->with('owner')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Groups/Index', [
            'mine' => $mine->map(fn (CommunityGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'kind' => $group->kind,
                'kind_label' => $group->isCourse() ? 'Course' : 'Club',
                'line' => $group->isCourse()
                    ? ($group->section?->scheduleLine() ?? 'Schedule not published')
                    : $group->description,
                'term' => $group->isCourse() ? ($group->section?->trimester?->name) : null,
                'is_archived' => $group->isArchived(),
                'notice' => $group->lifecycleNotice()['text'] ?? null,
                'members_count' => $group->members_count,
                'posts_count' => $group->posts_count,
                'url' => route('groups.show', $group),
            ])->values(),

            'groups' => $groups->through(fn (CommunityGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'type_label' => ucfirst($group->group_type),
                'description' => Str::limit($group->description, 140),
                'is_private' => $group->isPrivate(),
                'meta' => ($group->department ?: 'All departments')
                    .' · '.$group->members_count.' members'
                    .' · '.$group->posts_count.' posts'
                    .' · '.$group->files_count.' files'
                    .' · Owner '.($group->owner->full_name ?? 'Unknown'),
                'url' => route('groups.show', $group),
            ]),

            'filters' => ['q' => $q, 'type' => $type],
            'canCreate' => $viewer !== null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'group_type' => ['required', 'string', 'in:course,batch,project,club,community'],
            'department' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'visibility' => ['nullable', 'string', 'in:public,private'],
        ]);

        $slug = Str::slug($data['name']);
        $base = $slug;
        $i = 1;
        while (CommunityGroup::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        $group = CommunityGroup::create([
            'name' => $data['name'],
            'slug' => $slug,
            'group_type' => $data['group_type'],
            // Anything a student makes is an open group; classes and clubs
            // are created by the sync, never here.
            'kind' => CommunityGroup::KIND_OPEN,
            'department' => $data['department'] ?? null,
            'description' => $data['description'],
            'visibility' => $data['visibility'] ?? 'public',
            'owner_id' => $request->user()->id,
        ]);

        $group->members()->syncWithoutDetaching([$request->user()->id => ['role' => 'owner']]);

        return redirect()->route('groups.index')->with('success', 'Group created.');
    }

    public function show(Request $request, CommunityGroup $group)
    {
        $viewer = $request->user();

        $group->load(['owner', 'section.trimester']);

        $isMember = $group->hasMember($viewer);
        $isStaff = $group->isStaff($viewer);
        $isPending = ! $isMember && $group->isOpen() && $group->hasPending($viewer);

        // A private room shows its name and description to anybody, so it can
        // be found, and nothing else. A class is always private.
        $canSeeInside = $isMember;

        $members = $canSeeInside
            ? $group->members()
                ->with('profile')
                ->orderByRaw("FIELD(community_group_members.role, 'owner', 'teacher', 'assistant', 'officer') DESC")
                ->orderBy('users.full_name')
                ->limit(200)
                ->get()
            : collect();

        // Only an open group has a queue; derived rooms take their roster
        // from the registrar or from club_members.
        $pending = ($group->isOpen() && $isStaff)
            ? $group->pendingMembers()->with('profile')->get()
            : collect();

        $materials = $canSeeInside
            ? $group->materials()->with('uploader')->orderByDesc('created_at')->get()
            : collect();

        $notices = $canSeeInside
            ? Notice::where('group_id', $group->id)
                ->where('status', 'published')
                ->with('issuer')
                ->withCount('attachments')
                ->latest()
                ->get()
            : collect();

        // Opening the group is what clears the unread count beside Groups.
        if ($canSeeInside && $notices->isNotEmpty()) {
            $this->markNoticesRead($notices, $viewer);
        }

        // The wall deliberately skips scopeVisible, which is what keeps group
        // posts out of the feed. forViewer still applies, so a blocked or
        // muted author stays hidden here too.
        $posts = $canSeeInside
            ? Post::where('posts.group_id', $group->id)
                ->where('posts.is_deleted', false)
                ->forViewer($viewer)
                // Mirrors the feed's eager loads: _post.blade.php walks poll
                // votes and comment replies, and without these it is an N+1
                // per card.
                ->with([
                    'user.profile', 'reactions', 'saves', 'pollOptions.votes',
                    'comments.user.profile', 'comments.likes',
                    'comments.replies.user.profile', 'comments.replies.likes',
                ])
                ->withCount(['comments' => fn ($q) => $q->where('is_deleted', false)])
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        // Soonest deadline first, with open-ended work at the bottom -
        // which is the order a student cares about. Submission counts come
        // along so staff see progress without a query per row.
        $assignments = $canSeeInside
            ? \App\Models\Assignment::where('group_id', $group->id)
                ->with(['teacher:id,full_name', 'attachments'])
                ->withCount('submissions')
                ->orderByRaw('due_at IS NULL')
                ->orderBy('due_at')
                ->get()
            : collect();

        return view('groups.show', [
            'group' => $group,
            'members' => $members,
            'pending' => $pending,
            'materials' => $materials,
            'assignments' => $assignments,
            'notices' => $notices,
            'posts' => $posts,
            'memberCount' => $group->members()->count(),
            'isMember' => $isMember,
            'isStaff' => $isStaff,
            'isPending' => $isPending,
            'canSeeInside' => $canSeeInside,
            'canPost' => $group->canPost($viewer),
            'canPublish' => $group->canPublish($viewer),
            'canUpload' => $group->canUpload($viewer),
            'canManage' => $group->canManage($viewer),
            // Staff run the communication switch, so they always see the
            // chat. Everyone else sees it only once it is on - a chat tab
            // that opens onto a room you may not speak in is worse than no
            // tab at all.
            'chatVisible' => $canSeeInside
                && ($isStaff || $group->posting_mode === CommunityGroup::POSTING_EVERYONE),
            'notice' => $group->lifecycleNotice(),
        ]);
    }

    public function join(Request $request, CommunityGroup $group)
    {
        // A class or club roster is not something you opt into.
        abort_if($group->isDerived(), 403, 'Membership of this group follows your enrolment.');
        abort_if($group->isArchived(), 403, 'This group is archived.');

        $user = $request->user();

        if ($group->hasMember($user)) {
            return $this->respond($request, ['active' => true, 'count' => $group->members()->count()], 'You are already a member.');
        }

        // A private group is joined by asking. The request is the same pivot
        // row with a pending role, so no extra table is needed for it.
        $role = $group->isPrivate() ? CommunityGroup::PENDING : 'member';

        $group->members()->newPivotStatement()->updateOrInsert(
            ['group_id' => $group->id, 'user_id' => $user->id],
            ['role' => $role, 'joined_at' => now()]
        );

        return $this->respond(
            $request,
            ['active' => $role === 'member', 'pending' => $role === CommunityGroup::PENDING, 'count' => $group->members()->count()],
            $role === 'member'
                ? 'Joined '.$group->name.'.'
                : 'Request sent. The group owner will review it.'
        );
    }

    /** Leaving was simply missing: you could join a group but never get out. */
    public function leave(Request $request, CommunityGroup $group)
    {
        if ($group->isDerived()) {
            return back()->with('error', $group->isCourse()
                ? 'You are in this class because you are enrolled in it.'
                : 'Leave the club itself to leave this group.');
        }

        $user = $request->user();

        if ($group->isOwnedBy($user)) {
            return back()->with('error', 'The owner cannot leave. Delete the group instead.');
        }

        $this->pivot($group)->where('user_id', $user->id)->delete();

        return back()->with('success', 'You have left '.$group->name.'.');
    }

    /** Owner accepts somebody waiting at the door of a private open group. */
    public function approveMember(Request $request, CommunityGroup $group, User $user)
    {
        abort_unless($group->isOpen() && $group->canManage($request->user()), 403);

        $this->pivot($group)
            ->where('user_id', $user->id)
            ->where('role', CommunityGroup::PENDING)
            ->update(['role' => 'member', 'joined_at' => now()]);

        return back()->with('success', $user->full_name.' is now a member.');
    }

    /**
     * Removes somebody from the room. In a class that has to reach further
     * than the pivot: the enrolment is marked dropped too, otherwise the next
     * roster import would quietly put them straight back.
     */
    public function removeMember(Request $request, CommunityGroup $group, User $user)
    {
        abort_unless($group->canManage($request->user()), 403);

        if ($group->isOwnedBy($user)) {
            return back()->with('error', 'The person running this group cannot be removed.');
        }

        if ($group->isCourse() && $group->section) {
            CourseEnrollment::where('section_id', $group->section->id)
                ->where('user_id', $user->id)
                ->update(['status' => CourseEnrollment::DROPPED, 'dropped_at' => now()]);
        }

        if ($group->isClub()) {
            return back()->with('error', 'Remove this person from the club itself; the group follows it.');
        }

        $this->pivot($group)->where('user_id', $user->id)->delete();

        return back()->with('success', $user->full_name.' has been removed.');
    }

    /** The teacher's switch: announcements only, or a conversation. */
    public function togglePosting(Request $request, CommunityGroup $group)
    {
        abort_unless($group->canManage($request->user()), 403);
        abort_if($group->isArchived(), 403, 'This group is archived.');

        $open = $group->posting_mode === CommunityGroup::POSTING_EVERYONE;

        $group->forceFill([
            'posting_mode' => $open ? CommunityGroup::POSTING_STAFF : CommunityGroup::POSTING_EVERYONE,
        ])->save();

        return back()->with('success', $open
            ? 'Members can no longer post. Only you can now.'
            : 'Members can now post in this group.');
    }

    /** Puts up a slide deck or a reading. */
    public function publish(Request $request, CommunityGroup $group)
    {
        $user = $request->user();

        abort_unless($group->canUpload($user), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body' => ['nullable', 'string', 'max:2000'],
            'group_file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,txt,png,jpg,jpeg'],
        ]);

        $file = $request->file('group_file');
        $path = $file->store(self::FILE_FOLDER, self::FILE_DISK);

        CommunityGroupFile::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'kind' => CommunityGroupFile::MATERIAL,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => (int) $file->getSize(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Material added.');
    }

    /**
     * Posts an announcement. It is written to the notices table rather than
     * kept beside the files, so the same record shows up on the university
     * notice board for the people enrolled in this group - and nowhere else.
     */
    public function publishNotice(Request $request, CommunityGroup $group)
    {
        abort_unless($group->canPublish($request->user()), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:4000'],
            'is_urgent' => ['nullable', 'boolean'],
        ]);

        $notice = Notice::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'notice_type' => $group->isCourse() ? 'section' : 'department',
            'priority' => $request->boolean('is_urgent') ? 'high' : 'normal',
            'is_urgent' => $request->boolean('is_urgent'),
            'audience' => 'group',
            'department' => $group->department,
            'issuer_id' => $request->user()->id,
            'group_id' => $group->id,
            'status' => 'published',
        ]);

        // The person who wrote it has, by definition, read it.
        $this->markNoticesRead(collect([$notice]), $request->user());

        return back()->with('success', 'Notice posted. Everyone in this group will see it here and on the notice board.');
    }

    /** Takes an announcement down. */
    public function deleteNotice(Request $request, CommunityGroup $group, Notice $notice)
    {
        abort_unless((int) $notice->group_id === (int) $group->id, 404);

        $user = $request->user();
        abort_unless($group->canManage($user) || (int) $notice->issuer_id === (int) $user->id, 403);

        $notice->delete();

        return back()->with('success', 'Notice removed.');
    }

    /** Records that this person has seen these notices, without duplicates. */
    private function markNoticesRead($notices, ?User $viewer): void
    {
        if (! $viewer) {
            return;
        }

        $rows = $notices->map(fn ($notice) => [
            'notice_id' => $notice->id,
            'user_id' => $viewer->id,
            'read_at' => now(),
        ])->all();

        if ($rows !== []) {
            DB::table('notice_reads')->insertOrIgnore($rows);
        }
    }

    /** Whoever put it up, or whoever runs the room, can take it down. */
    public function deleteFile(Request $request, CommunityGroup $group, CommunityGroupFile $file)
    {
        abort_unless($file->group_id === $group->id, 404);
        abort_if($group->isArchived(), 403, 'This group is archived.');

        $user = $request->user();
        abort_unless($group->canManage($user) || (int) $file->user_id === (int) $user->id, 403);

        if ($file->hasFile()) {
            foreach ([self::FILE_DISK, 'public'] as $disk) {
                Storage::disk($disk)->delete($file->file_path);
            }
        }

        $file->delete();

        return back()->with('success', 'Removed.');
    }

    /**
     * The only way to read a group file. Membership is checked on every
     * download rather than once at upload, so removing somebody from a group
     * actually takes their access away. Archived rooms still allow this - that
     * is the whole point of archiving rather than deleting.
     */
    public function downloadFile(Request $request, CommunityGroup $group, CommunityGroupFile $file)
    {
        abort_unless($file->group_id === $group->id, 404);
        abort_unless($file->hasFile(), 404, 'That notice has no attachment.');

        abort_unless(
            $group->hasMember($request->user()),
            403,
            'Only members of this group can open its files.'
        );

        // Files uploaded before the move still sit on the public disk.
        foreach ([self::FILE_DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($file->file_path)) {
                return Storage::disk($disk)->download($file->file_path, $file->original_name ?: $file->title);
            }
        }

        abort(404, 'That file is no longer on the server.');
    }

    public function update(Request $request, CommunityGroup $group)
    {
        abort_unless($group->canManage($request->user()), 403);
        abort_if($group->isArchived(), 403, 'This group is archived.');

        // A class or club takes its name, description and roster from the
        // record behind it, so only an open group is editable here.
        abort_if($group->isDerived(), 403, 'This group is kept in step with its course or club.');

        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'department' => ['nullable', 'string', 'max:120'],
            'group_type' => ['required', 'string', 'in:course,batch,project,club,community'],
            'visibility' => ['required', 'string', 'in:public,private'],
        ]);

        $group->update($data);

        return back()->with('success', 'Group updated.');
    }

    public function destroy(Request $request, CommunityGroup $group)
    {
        abort_unless($group->isOwnedBy($request->user()), 403);
        abort_if($group->isDerived(), 403, 'A class or club group is removed by removing the course or club.');

        foreach ($group->files as $file) {
            if ($file->hasFile()) {
                foreach ([self::FILE_DISK, 'public'] as $disk) {
                    Storage::disk($disk)->delete($file->file_path);
                }
            }
        }

        // Members, files and posts all cascade from the foreign keys.
        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Group deleted.');
    }

    /** Raw pivot query for this group, used where roles are written. */
    private function pivot(CommunityGroup $group)
    {
        return DB::table('community_group_members')->where('group_id', $group->id);
    }
}
