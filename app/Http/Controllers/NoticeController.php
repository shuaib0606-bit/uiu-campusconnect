<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\NoticeAttachment;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoticeController extends Controller
{
    public const TYPES = [
        'exam' => 'Exams', 'routine' => 'Class routines', 'payment' => 'Payments',
        'admission' => 'Admission', 'scholarship' => 'Scholarship', 'transport' => 'Transport',
        'department' => 'Department', 'section' => 'Sections', 'academic' => 'Academic',
        'university' => 'University',
    ];

    public function index(Request $request)
    {
        $type = trim((string) $request->get('type', ''));
        $department = trim((string) $request->get('department', ''));
        $batch = trim((string) $request->get('batch', ''));
        $semester = trim((string) $request->get('semester', ''));
        $urgent = $request->boolean('urgent');
        $q = trim((string) $request->get('q', ''));

        $base = Notice::where('status', 'published');

        $stats = [
            'all' => (clone $base)->count(),
            'urgent' => (clone $base)->where(fn ($q2) => $q2->where('is_urgent', true)->orWhereIn('priority', ['urgent', 'high']))->count(),
            'academic' => (clone $base)->whereIn('notice_type', ['exam', 'routine', 'section', 'academic'])->count(),
        ];

        $featured = (clone $base)->with('issuer')->withCount('attachments')
            ->orderByRaw('(is_urgent = 1 OR priority IN (\'urgent\',\'high\')) DESC')
            ->latest()
            ->first();

        $notices = $base->with('issuer')->withCount('attachments')
            ->when($type !== '', fn ($q2) => $q2->where('notice_type', $type))
            ->when($department !== '', fn ($q2) => $q2->where('department', 'like', "%{$department}%"))
            ->when($batch !== '', fn ($q2) => $q2->where('batch', 'like', "%{$batch}%"))
            ->when($semester !== '', fn ($q2) => $q2->where('semester', 'like', "%{$semester}%"))
            ->when($urgent, fn ($q2) => $q2->where(fn ($q3) => $q3->where('is_urgent', true)->orWhereIn('priority', ['urgent', 'high'])))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($q3) use ($q) {
                    $q3->where('title', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%")->orWhere('notice_type', 'like', "%{$q}%");
                });
            })
            ->when($featured, fn ($q2) => $q2->where('id', '!=', $featured->id))
            ->orderByRaw('(is_urgent = 1 OR priority IN (\'urgent\',\'high\')) DESC')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('notices.index', [
            'notices' => $notices,
            'featured' => $featured,
            'stats' => $stats,
            'types' => self::TYPES,
            'type' => $type,
            'department' => $department,
            'batch' => $batch,
            'semester' => $semester,
            'urgent' => $urgent,
            'q' => $q,
            'canIssue' => $request->user() && $request->user()->canIssueNotices(),
        ]);
    }

    public function show(Request $request, Notice $notice)
    {
        $notice->load(['issuer', 'attachments']);

        $related = Notice::where('status', 'published')
            ->where('id', '!=', $notice->id)
            ->where('notice_type', $notice->notice_type)
            ->withCount('attachments')
            ->latest()
            ->limit(4)
            ->get();

        return view('notices.show', [
            'notice' => $notice,
            'related' => $related,
            'types' => self::TYPES,
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
            'department' => ['nullable', 'string', 'max:120'],
            'batch' => ['nullable', 'string', 'max:60'],
            'semester' => ['nullable', 'string', 'max:60'],
            'is_urgent' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        unset($data['attachments']);

        $notice = Notice::create([...$data, 'is_urgent' => $request->boolean('is_urgent'), 'issuer_id' => $request->user()->id]);

        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }

            NoticeAttachment::create([
                'notice_id' => $notice->id,
                'file_path' => $file->store('notices', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'created_at' => now(),
            ]);
        }

        $this->notifyAudience($notice);

        return back()->with('success', 'Notice published.');
    }

    /**
     * Sends a published notice to its audience, taken from the notice's audience and department
     * columns. Administrative notices ignore blocks and mutes.
     */
    protected function notifyAudience(Notice $notice): void
    {
        // The status column default is applied by the database, so a freshly created model has it unset.
        if (in_array($notice->status, ['draft', 'archived'], true)) {
            return;
        }

        $label = $notice->isImportant() ? 'Urgent notice' : 'University notice';
        $scope = filled($notice->department) ? ' ('.$notice->department.')' : '';

        Notifier::broadcast(
            Notifier::audienceQuery($notice->audience, $notice->department),
            $notice->issuer ?: request()->user(),
            Notifier::NOTICE,
            'notice',
            $notice->id,
            $label.$scope.': '.$notice->title,
            respectSocialState: false,
        );
    }
}
