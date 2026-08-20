<?php

namespace App\Http\Controllers;

use App\Models\CommunityGroup;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index()
    {
        $groups = CommunityGroup::withCount('members')->latest()->paginate(12);

        return view('groups.index', compact('groups'));
    }

    public function show(CommunityGroup $group)
    {
        $group->load(['members', 'files.uploader']);

        return view('groups.show', compact('group'));
    }

    public function join(Request $request, CommunityGroup $group)
    {
        $group->members()->syncWithoutDetaching([$request->user()->id => ['role' => 'member']]);

        return $this->respond($request, ['active' => true, 'count' => $group->members()->count()], 'Joined '.$group->name.'.');
    }
}
