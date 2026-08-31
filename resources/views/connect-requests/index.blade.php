<x-app-layout title="Connection Applications - UIU CampusConnect">
    <div class="mx-auto max-w-3xl">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-4 p-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">Admin-handled access</p>
                <h1 class="mt-1 text-[22px] font-semibold tracking-tight">Connection Applications</h1>
                <p class="section-sub mt-1 max-w-lg">Students apply here to contact faculty or university officials. Admin reviews every application first, so faculty and officials don't receive requests directly.</p>
            </div>
            <div class="text-center">
                <div class="text-2xl font-semibold">{{ $applications->count() }}</div>
                <div class="section-sub">{{ auth()->user()->isAdmin() ? ucfirst($tab).' applications' : 'records' }}</div>
            </div>
        </div>

        @if (session('success'))<div class="surface-flat mb-4 border-l-4 border-l-emerald-500 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="surface-flat mb-4 border-l-4 border-l-red-500 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        @if (auth()->user()->isAdmin())
            <div class="surface-flat mb-5 flex gap-1 p-1">
                @foreach (['pending' => 'Pending', 'reviewing' => 'Reviewing', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $key => $label)
                    <a href="{{ route('connect-requests.index', ['tab' => $key]) }}" class="flex-1 rounded-xl px-3 py-2 text-center text-sm font-medium {{ $tab === $key ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300' }}">{{ $label }}</a>
                @endforeach
            </div>
        @elseif (auth()->user()->role === 'student')
            <div class="surface-flat mb-5 p-1"><span class="block rounded-xl bg-primary-500 px-3 py-2 text-center text-sm font-medium text-white">My Applications</span></div>
        @else
            <div class="surface-flat mb-5 p-1"><span class="block rounded-xl bg-primary-500 px-3 py-2 text-center text-sm font-medium text-white">Approved Students</span></div>
        @endif

        @if ($target)
            <div class="surface mb-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700 font-semibold dark:bg-primary-900/40 dark:text-primary-200">{{ $target->avatar_initials }}</span>
                    <div>
                        <h2 class="text-[15px] font-semibold">Apply to connect with {{ $target->full_name }}</h2>
                        <p class="section-sub">{{ ucfirst($target->role) }}@if($target->designation) &middot; {{ $target->designation }}@endif@if($target->department) &middot; {{ $target->department }}@endif</p>
                    </div>
                </div>

                @if (\App\Services\SocialRules::areConnected(auth()->user(), $target))
                    <div class="surface-flat mt-4 border-l-4 border-l-emerald-500 px-4 py-3 text-sm">You are already approved to connect with this user.</div>
                    <a href="{{ route('messages.show', $target) }}" class="btn-primary mt-3 inline-flex">Open message</a>
                @elseif ($targetApplication && in_array($targetApplication->status, ['pending', 'reviewing']))
                    <div class="surface-flat mt-4 border-l-4 border-l-amber-500 px-4 py-3 text-sm">Your application is {{ $targetApplication->statusLabel() }}.</div>
                @elseif ($targetApplication && $targetApplication->status === 'approved')
                    <div class="surface-flat mt-4 border-l-4 border-l-emerald-500 px-4 py-3 text-sm">This application was approved.</div>
                @else
                    @if (!$targetAllowed && $targetMessage)
                        <div class="surface-flat mt-4 border-l-4 border-l-red-500 px-4 py-3 text-sm">{{ $targetMessage }}</div>
                    @endif
                    <form method="POST" action="{{ route('connect-requests.apply', $target) }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="block"><span class="mb-1 block text-sm font-medium">Purpose</span>
                            <select name="purpose" class="field">
                                <option>Academic support</option>
                                <option>Course advising</option>
                                <option>Project supervision</option>
                                <option>Club or event work</option>
                                <option>Administrative help</option>
                                <option>Other</option>
                            </select>
                        </label>
                        <label class="block"><span class="mb-1 block text-sm font-medium">Course / Department</span>
                            <input type="text" name="course_department" placeholder="Example: CSE, thesis, project, course code" class="field">
                        </label>
                        <label class="block"><span class="mb-1 block text-sm font-medium">Reason for contact</span>
                            <textarea name="message" rows="4" required placeholder="Explain why admin should approve this connection..." class="field"></textarea>
                        </label>
                        <button type="submit" class="btn-primary">Send application to admin</button>
                    </form>
                @endif
            </div>
        @elseif (request('target_id') && auth()->user()->role === 'student')
            <div class="surface-flat mb-5 border-l-4 border-l-red-500 px-4 py-3 text-sm">Faculty/official account not found.</div>
        @endif

        <div class="space-y-4">
            @forelse ($applications as $app)
                <article class="surface p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-1.5 text-[15px] font-semibold">
                                <a href="{{ route('profile.show', $app->student) }}" class="hover:underline">{{ $app->student->full_name }}</a>
                                <span class="text-ink-400">&rarr;</span>
                                <a href="{{ route('profile.show', $app->target) }}" class="hover:underline">{{ $app->target->full_name }}</a>
                            </div>
                            <p class="section-sub mt-0.5">
                                {{ $app->student->email }}
                                @if($app->student->department) &middot; {{ $app->student->department }}@endif
                                &middot; Target: {{ ucfirst($app->target->role) }}
                            </p>
                        </div>
                        <span class="chip shrink-0
                            @class([
                                '!bg-amber-500/10 !text-amber-700 dark:!text-amber-300' => in_array($app->status, ['pending', 'reviewing']),
                                '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-300' => $app->status === 'approved',
                                '!bg-red-500/10 !text-red-700 dark:!text-red-300' => in_array($app->status, ['rejected', 'cancelled']),
                            ])">{{ $app->statusLabel() }}</span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        <div><span class="block text-xs font-semibold uppercase text-ink-400">Purpose</span>{{ $app->purpose }}</div>
                        <div><span class="block text-xs font-semibold uppercase text-ink-400">Course/Dept</span>{{ $app->course_department ?: 'Not specified' }}</div>
                        <div><span class="block text-xs font-semibold uppercase text-ink-400">Submitted</span>{{ $app->created_at->format('M j, Y g:i A') }}</div>
                        <div><span class="block text-xs font-semibold uppercase text-ink-400">Last update</span>{{ $app->updated_at->format('M j, Y g:i A') }}</div>
                    </div>

                    @if ($app->message)
                        <div class="mt-3 rounded-xl bg-ink-900/[0.03] dark:bg-white/[0.04] p-3 text-sm">
                            <span class="mb-1 block text-xs font-semibold uppercase text-ink-400">Student message</span>{{ $app->message }}
                        </div>
                    @endif

                    @if ($app->admin_note)
                        <div class="mt-3 rounded-xl bg-primary-500/5 p-3 text-sm">
                            <span class="mb-1 block text-xs font-semibold uppercase text-primary-600 dark:text-primary-300">Admin note</span>{{ $app->admin_note }}
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a href="{{ route('profile.show', $app->student) }}" class="btn-secondary !px-3 !py-1.5 text-xs">View student</a>
                        <a href="{{ route('profile.show', $app->target) }}" class="btn-secondary !px-3 !py-1.5 text-xs">View faculty/official</a>
                        @if ($app->status === 'approved' && $app->student_id === auth()->id())
                            <a href="{{ route('messages.show', $app->target) }}" class="btn-primary !px-3 !py-1.5 text-xs">Message</a>
                        @endif
                    </div>

                    @if (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('connect-requests.review', $app) }}" class="mt-4 space-y-2 border-t border-ink-100 dark:border-ink-800/70 pt-4">
                            @csrf
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <label class="block"><span class="mb-1 block text-xs font-medium text-ink-500">Admin note</span>
                                <textarea name="admin_note" rows="2" placeholder="Add reviewer note or decision reason..." class="field !py-2 text-sm">{{ $app->admin_note }}</textarea>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                @unless (in_array($app->status, ['approved', 'rejected', 'cancelled']))
                                    <button type="submit" name="application_action" value="reviewing" class="btn-secondary !px-3 !py-1.5 text-xs">Review</button>
                                    <button type="submit" name="application_action" value="approve" class="btn-primary !px-3 !py-1.5 text-xs">Approve</button>
                                    <button type="submit" name="application_action" value="reject" class="btn-secondary !px-3 !py-1.5 text-xs">Reject</button>
                                @endunless
                            </div>
                        </form>
                        <form method="POST" action="{{ route('connect-requests.suspend-student', $app) }}" class="mt-2" onsubmit="return confirm('Suspend this student account?')">
                            @csrf
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <button type="submit" class="btn-danger !px-3 !py-1.5 text-xs">Suspend student</button>
                        </form>
                    @elseif ($app->student_id === auth()->id() && in_array($app->status, ['pending', 'reviewing']))
                        <form method="POST" action="{{ route('connect-requests.cancel', $app) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Cancel application</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="empty-state">
                    <span class="empty-icon"><x-ui-icon name="users" class="h-6 w-6" /></span>
                    <h3>No applications found</h3>
                    <p>{{ auth()->user()->isAdmin() ? 'There are no applications in this tab yet.' : 'Your connection application history will appear here.' }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
