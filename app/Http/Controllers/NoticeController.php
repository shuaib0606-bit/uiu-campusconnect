<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $notices = Notice::where('status', 'published')->with('issuer')->latest()->paginate(15);

        return view('notices.index', [
            'notices' => $notices,
            'canIssue' => $request->user()->canIssueNotices(),
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->canIssueNotices()) {
            abort(403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string'],
            'notice_type' => ['required', 'string', 'max:80'],
            'priority' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'audience' => ['required', 'string', 'max:80'],
        ]);

        Notice::create([...$data, 'issuer_id' => $request->user()->id]);

        return back()->with('success', 'Notice published.');
    }
}
