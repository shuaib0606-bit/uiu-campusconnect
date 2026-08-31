@php
    $statusChip = fn ($status) => match ($status) {
        'active' => '!bg-emerald-500/10 !text-emerald-600 dark:!text-emerald-300',
        'pending' => '!bg-amber-500/10 !text-amber-600 dark:!text-amber-300',
        'suspended', 'rejected' => '!bg-red-500/10 !text-red-600 dark:!text-red-300',
        default => '',
    };
@endphp
<x-app-layout title="User Approvals - UIU CampusConnect">
    <div class="mx-auto max-w-4xl">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-6 bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-white">
            <div class="max-w-md">
                <p class="text-xs font-semibold uppercase tracking-wide text-white/80">Master admin</p>
                <h1 class="mt-1 text-[22px] font-semibold">User Approval Center</h1>
                <p class="mt-1 text-sm text-white/85">Approve every new signup before the user becomes valid. Student requests should include a UIU ID card for verification.</p>
            </div>
            <div class="grid shrink-0 grid-cols-4 gap-2">
                <div class="rounded-2xl bg-white/15 px-3 py-2.5 text-center"><strong class="block text-lg">{{ $statusCounts['pending'] ?? 0 }}</strong><span class="text-xs text-white/80">Pending</span></div>
                <div class="rounded-2xl bg-white/15 px-3 py-2.5 text-center"><strong class="block text-lg">{{ $statusCounts['active'] ?? 0 }}</strong><span class="text-xs text-white/80">Active</span></div>
                <div class="rounded-2xl bg-white/15 px-3 py-2.5 text-center"><strong class="block text-lg">{{ $statusCounts['rejected'] ?? 0 }}</strong><span class="text-xs text-white/80">Rejected</span></div>
                <div class="rounded-2xl bg-white/15 px-3 py-2.5 text-center"><strong class="block text-lg">{{ $statusCounts['suspended'] ?? 0 }}</strong><span class="text-xs text-white/80">Suspended</span></div>
            </div>
        </div>

        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-3 p-3">
            <nav class="flex flex-wrap gap-1.5">
                @foreach (['pending' => 'Pending requests', 'active' => 'Active users', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'all' => 'All users'] as $key => $label)
                    <a href="{{ route('user-approvals.index', ['tab' => $key]) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === $key ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <form method="GET" class="flex gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input name="q" value="{{ $q }}" placeholder="Search name, email, ID, department" class="field w-64">
            </form>
        </div>

        @if ($requests->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="shield" class="h-6 w-6" /></span>
                <h3>No records found</h3>
                <p>There are no users in this approval category right now.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($requests as $req)
                    <article class="surface grid gap-4 p-5 sm:grid-cols-[1fr_260px]">
                        <div class="flex gap-3">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">{{ $req->avatar_initials }}</span>
                            <div class="min-w-0">
                                <h2 class="flex flex-wrap items-center gap-2 text-[15px] font-semibold">{{ $req->full_name }}<span class="chip {{ $statusChip($req->status) }}">{{ ucfirst($req->status) }}</span></h2>
                                <p class="section-sub">{{ ucfirst($req->role) }}{{ $req->department ? ' · '.$req->department : '' }}{{ $req->student_id ? ' · Student ID: '.$req->student_id : '' }}{{ $req->employee_id ? ' · Employee ID: '.$req->employee_id : '' }}</p>
                                <p class="mt-1 text-sm">{{ $req->email }}</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded-lg bg-ink-900/[0.03] p-2 dark:bg-white/[0.04]"><span class="block font-semibold text-ink-400">Requested</span>{{ optional($req->signup_requested_at ?? $req->created_at)->format('M j, Y g:i A') }}</div>
                                    <div class="rounded-lg bg-ink-900/[0.03] p-2 dark:bg-white/[0.04]"><span class="block font-semibold text-ink-400">Reviewed by</span>{{ $req->signupReviewer?->full_name ?: 'Not reviewed' }}</div>
                                    <div class="rounded-lg bg-ink-900/[0.03] p-2 dark:bg-white/[0.04]"><span class="block font-semibold text-ink-400">Review note</span>{{ $req->signup_review_note ?: 'No note' }}</div>
                                    <div class="rounded-lg bg-ink-900/[0.03] p-2 dark:bg-white/[0.04]"><span class="block font-semibold text-ink-400">Verified</span>{{ $req->is_verified ? 'Yes' : 'No' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('profile.show', $req) }}" target="_blank" class="btn-secondary !px-3 !py-1.5 text-xs">View profile</a>
                                @if ($req->role === 'student')
                                    @if ($req->student_id_card_path)
                                        <a href="{{ Storage::url($req->student_id_card_path) }}" target="_blank" class="btn-secondary !px-3 !py-1.5 text-xs">View UIU ID</a>
                                    @else
                                        <span class="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">No UIU ID uploaded</span>
                                    @endif
                                @endif
                            </div>
                            <form method="POST" action="{{ route('user-approvals.review', $req) }}">
                                @csrf
                                <input type="hidden" name="tab" value="{{ $tab }}">
                                <textarea name="admin_note" placeholder="Optional admin note" rows="2" class="field mb-2"></textarea>
                                <div class="flex flex-wrap gap-2">
                                    @if ($req->status !== 'active')<button type="submit" name="decision" value="approve" class="btn-primary !px-3 !py-1.5 text-xs">Approve</button>@endif
                                    @if ($req->status === 'pending')<button type="submit" name="decision" value="reject" class="btn-secondary !px-3 !py-1.5 text-xs">Reject</button>@endif
                                    @if ($req->status === 'active')
                                        <button type="submit" name="decision" value="suspend" class="btn-danger !px-3 !py-1.5 text-xs">Suspend</button>
                                    @elseif ($req->status === 'suspended')
                                        <button type="submit" name="decision" value="reactivate" class="btn-primary !px-3 !py-1.5 text-xs">Reactivate</button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
