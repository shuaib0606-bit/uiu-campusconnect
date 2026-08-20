<?php

namespace App\Http\Controllers;

use App\Models\SectionAnnouncement;
use Illuminate\Http\Request;

class SectionAnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $sections = SectionAnnouncement::with('issuer')->latest()->get();

        return view('administration.index', [
            'sections' => $sections,
            'canManage' => $request->user()->canIssueSectionAnnouncements(),
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->canIssueSectionAnnouncements()) {
            abort(403);
        }

        $data = $request->validate([
            'course_code' => ['required', 'string', 'max:30'],
            'section_name' => ['required', 'string', 'max:30'],
            'schedule_pattern' => ['required', 'string', 'max:60'],
            'start_time' => ['required'],
            'end_time' => ['required'],
            'room' => ['required', 'string', 'max:120'],
            'seat_limit' => ['required', 'integer', 'min:1'],
        ]);

        SectionAnnouncement::create([
            ...$data,
            'title' => $data['course_code'].' '.$data['section_name'].' update',
            'issuer_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Section update published.');
    }
}
