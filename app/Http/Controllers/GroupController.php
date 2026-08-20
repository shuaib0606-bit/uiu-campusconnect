<?php

namespace App\Http\Controllers;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupFile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $type = trim((string) $request->get('type', ''));

        $groups = CommunityGroup::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('department', 'like', "%{$q}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('group_type', $type))
            ->withCount(['members', 'files'])
            ->with('owner')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('groups.index', compact('groups', 'q', 'type'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'group_type' => ['required', 'string', 'in:course,batch,project,club,community'],
            'department' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
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
            'department' => $data['department'] ?? null,
            'description' => $data['description'],
            'owner_id' => $request->user()->id,
        ]);

        $group->members()->syncWithoutDetaching([$request->user()->id => ['role' => 'owner']]);

        return redirect()->route('groups.index')->with('success', 'Group created.');
    }

    public function show(CommunityGroup $group)
    {
        $group->load(['owner']);
        $members = $group->members()->with('profile')->orderByDesc('community_group_members.joined_at')->limit(30)->get();
        $files = $group->files()->with('uploader')->latest()->get();
        $isMember = auth()->check() && $group->members()->where('users.id', auth()->id())->exists();

        return view('groups.show', compact('group', 'members', 'files', 'isMember'));
    }

    public function join(Request $request, CommunityGroup $group)
    {
        $group->members()->syncWithoutDetaching([$request->user()->id => ['role' => 'member']]);

        return $this->respond($request, ['active' => true, 'count' => $group->members()->count()], 'Joined '.$group->name.'.');
    }

    public function uploadFile(Request $request, CommunityGroup $group)
    {
        $user = $request->user();
        if (! $group->members()->where('users.id', $user->id)->exists()) {
            abort(403, 'Join the group to upload files.');
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:190'],
            'group_file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx'],
        ]);

        $file = $request->file('group_file');
        $path = $file->store('group-files', 'public');

        CommunityGroupFile::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'title' => $data['title'] ?: $file->getClientOriginalName(),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'created_at' => now(),
        ]);

        return $this->respond($request, [], 'Resource uploaded.');
    }
}
