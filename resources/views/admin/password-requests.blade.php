<x-app-layout title="Password recovery - UIU CampusConnect">
    <div class="mx-auto max-w-4xl">

        <div class="hero-panel mb-5 p-7">
            <x-ui-icon name="lock" class="hero-icon" />
            <div class="relative z-10">
                <p class="eyebrow"><x-ui-icon name="shield" class="h-3.5 w-3.5" />Admin only</p>
                <h1 class="hero-title mt-2">Password recovery requests</h1>
                <p class="section-sub mt-2 max-w-lg">
                    Verify who the person is before approving. Approving issues a new temporary
                    password and forces them to change it at next sign-in.
                </p>
            </div>
        </div>

        @if ($issued)
            <div class="surface mb-5 border-l-4 border-l-emerald-500 p-5">
                <h2 class="inline-flex items-center gap-1.5 text-[15px] font-semibold">
                    <x-ui-icon name="check-circle" class="h-4 w-4 text-emerald-500" />
                    Temporary password for {{ $issuedFor }}
                </h2>
                <p class="section-sub mt-1">Give this to them directly. It is shown once and cannot be recovered from this screen again.</p>
                <p class="mt-3 select-all rounded-xl bg-ink-900/[0.05] px-4 py-3 font-mono text-lg tracking-wider dark:bg-white/[0.07]">{{ $issued }}</p>
                <p class="mt-2 text-xs text-ink-500 dark:text-ink-400">They will be required to set their own password before they can use the site.</p>
            </div>
        @endif

        <div class="surface mb-5 flex flex-wrap gap-1.5 p-3">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                <a href="{{ route('password-requests.index', ['tab' => $key]) }}" class="filter-pill {{ $tab === $key ? 'is-active' : '' }}">
                    {{ $label }}
                    @if ($key !== 'all' && ($counts[$key] ?? 0) > 0)
                        <span class="ml-1 text-xs opacity-70">{{ $counts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        @if ($requests->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="lock" class="h-6 w-6" /></span>
                <h3>Nothing here</h3>
                <p>No {{ $tab === 'all' ? '' : $tab }} recovery requests.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($requests as $req)
                    <article class="surface p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($req->isUnmatched())
                                        <span class="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">No matching account</span>
                                    @else
                                        <span class="chip">{{ $req->user->roleLabel() }}</span>
                                    @endif

                                    @if ($req->status === 'pending')
                                        <span class="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">Pending</span>
                                    @elseif ($req->status === 'approved')
                                        <span class="chip !bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-300">Approved</span>
                                    @else
                                        <span class="chip !bg-red-500/10 !text-red-600 dark:!text-red-300">Rejected</span>
                                    @endif
                                </div>

                                <h2 class="mt-1.5 text-[15px] font-semibold">
                                    {{ $req->user?->full_name ?? 'Unknown person' }}
                                </h2>
                                <p class="section-sub mt-0.5">
                                    Submitted <span class="font-medium">{{ $req->submitted_identifier }}</span>
                                    &middot; {{ $req->created_at->diffForHumans() }}
                                    @if ($req->ip_address) &middot; from {{ $req->ip_address }} @endif
                                </p>

                                @if ($req->user)
                                    <p class="section-sub mt-0.5">
                                        {{ $req->user->email }}
                                        @if ($req->user->department) &middot; {{ $req->user->department }} @endif
                                        @if ($req->user->student_id) &middot; ID {{ $req->user->student_id }} @endif
                                    </p>
                                @endif

                                @if ($req->note)
                                    <p class="mt-2 rounded-xl bg-ink-900/[0.03] px-3.5 py-2 text-sm dark:bg-white/[0.04]">{{ $req->note }}</p>
                                @endif

                                @if (! $req->isPending())
                                    <p class="section-sub mt-2">
                                        Reviewed by {{ $req->reviewer?->full_name ?? 'unknown' }}
                                        @if ($req->reviewed_at) &middot; {{ $req->reviewed_at->format('M j, Y g:i A') }} @endif
                                        @if ($req->review_note) &mdash; {{ $req->review_note }} @endif
                                    </p>
                                @endif
                            </div>

                            @if ($req->isPending())
                                <form method="POST" action="{{ route('password-requests.review', $req) }}" class="shrink-0 space-y-2 sm:w-56">
                                    @csrf
                                    <input name="review_note" maxlength="500" placeholder="Note (optional)" class="field !py-1.5 text-xs">
                                    <div class="flex gap-2">
                                        @if (! $req->isUnmatched())
                                            <button type="submit" name="decision" value="approve" class="btn-primary flex-1 !px-3 !py-1.5 text-xs"
                                                onclick="return confirm('Issue a new temporary password for {{ addslashes($req->user->full_name) }}? Their current password stops working immediately.')">
                                                Approve
                                            </button>
                                        @endif
                                        <button type="submit" name="decision" value="reject" class="btn-secondary flex-1 !px-3 !py-1.5 text-xs">Reject</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-6">{{ $requests->links() }}</div>
        @endif
    </div>
</x-app-layout>
