<?php

namespace App\Services\Ai\Tools;

use App\Models\Notice;
use App\Services\Ai\Links;

/**
 * The notice board, through Notice::visibleTo - the same scope the board
 * uses. A class or club notice reaches only that group's members.
 */
class NoticesTool extends Tool
{
    public function name(): string
    {
        return 'notices';
    }

    public function description(): string
    {
        return 'University and class notices the asker may see, newest first, with whether they have read them.';
    }

    public function parameters(): array
    {
        return self::object([
            'query' => self::text('Optional words to look for, e.g. "exam", "fee", "holiday".'),
            'unread_only' => ['type' => 'boolean', 'description' => 'Only notices not yet opened.'],
        ]);
    }

    public function run(array $arguments): array
    {
        $query = $this->arg($arguments, 'query');
        $unreadOnly = filter_var($arguments['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $like = '%'.addcslashes($query, '%_\\').'%';
        $me = $this->viewer->id;

        $read = fn ($q) => $q->selectRaw('1')->from('notice_reads')
            ->whereColumn('notice_reads.notice_id', 'notices.id')
            ->where('notice_reads.user_id', $me);

        $notices = Notice::where('status', 'published')
            ->visibleTo($this->viewer)
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', $like)
                ->orWhere('body', 'like', $like)
                ->orWhere('notice_type', 'like', $like)))
            ->when($unreadOnly, fn ($q) => $q->whereNotNull('group_id')->whereNotExists($read))
            ->with(['issuer:id,full_name', 'group:id,name'])
            ->withExists(['readers as read_by_me' => fn ($q) => $q->where('users.id', $me)])
            ->latest()
            ->limit(10)
            ->get();

        return ['notices' => $notices->map(fn (Notice $n) => array_filter([
            'title' => $n->title,
            'link' => Links::notice($n),
            'text' => $this->excerpt($n->body, 400),
            'type' => $n->notice_type,
            'urgent' => $n->is_urgent ? true : null,
            'for' => $n->group?->name ?? 'the whole university',
            'by' => $n->issuer?->full_name,
            'posted' => $n->created_at?->toDayDateTimeString(),
            // Only class and club notices track reading; a university-wide
            // notice has no read state to report.
            'read' => $n->group_id ? (bool) $n->read_by_me : null,
        ], fn ($v) => $v !== null && $v !== ''))->values()->all()];
    }
}
