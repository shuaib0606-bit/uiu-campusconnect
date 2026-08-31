@php
    $networkRoleLabels = ['student' => 'Student', 'faculty' => 'Faculty', 'official' => 'Official', 'admin' => 'Admin'];
@endphp
<x-app-layout title="Network - UIU CampusConnect">
    <div class="mx-auto max-w-6xl">
        <div class="section-heading mb-5 flex-wrap gap-4">
            <div>
                <h1>University Network</h1>
                <p class="section-sub">Find students, faculty, officials, and active campus members.</p>
            </div>
            <form method="GET" class="flex flex-1 flex-wrap justify-end gap-2 sm:flex-nowrap">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name, department, title, or expertise" class="field min-w-[220px] flex-1 sm:max-w-xs">
                <select name="role" class="field w-auto">
                    <option value="">All roles</option>
                    <option value="student" @selected($role === 'student')>Students</option>
                    <option value="faculty" @selected($role === 'faculty')>Faculty</option>
                    <option value="official" @selected($role === 'official')>Officials</option>
                    <option value="admin" @selected($role === 'admin')>Admin</option>
                </select>
                <button type="submit" class="btn-primary shrink-0">Search</button>
            </form>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="stat-card items-center text-center">
                <span class="stat-value">{{ $roleCounts['student'] ?? 0 }}</span>
                <span class="stat-label">Students</span>
            </div>
            <div class="stat-card items-center text-center">
                <span class="stat-value">{{ $roleCounts['faculty'] ?? 0 }}</span>
                <span class="stat-label">Faculty</span>
            </div>
            <div class="stat-card items-center text-center">
                <span class="stat-value">{{ $roleCounts['official'] ?? 0 }}</span>
                <span class="stat-label">Officials</span>
            </div>
            <div class="stat-card items-center text-center">
                <span class="stat-value">{{ $roleCounts['admin'] ?? 0 }}</span>
                <span class="stat-label">Admin</span>
            </div>
        </div>

        @if ($people->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="users" class="h-6 w-6" /></span>
                <h3>No people found</h3>
                <p>Try another keyword or clear the role filter.</p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($people as $person)
                    @php
                        $roleLabel = $networkRoleLabels[$person->role] ?? ucfirst($person->role ?: 'Member');
                        $special = $specialRoles[$person->id] ?? null;
                        $title = $person->designation ?: $roleLabel;
                        $department = $person->department ?: 'United International University';
                        $bio = \Illuminate\Support\Str::limit((string) ($person->profile->bio ?? ''), 110);
                        $skills = array_slice(array_filter(array_map('trim', explode(',', (string) ($person->profile->skills ?? '')))), 0, 3);
                        $friendship = \App\Services\SocialRules::friendshipStatus(auth()->user(), $person);
                    @endphp
                    <article class="surface surface-hover flex flex-col p-5">
                        <div class="flex items-start gap-3">
                            <a href="{{ route('profile.show', $person) }}" class="shrink-0">
                                <span class="grid h-14 w-14 place-items-center rounded-full bg-primary-100 text-primary-700 text-lg font-semibold dark:bg-primary-900/40 dark:text-primary-200">{{ $person->avatar_initials }}</span>
                            </a>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <a href="{{ route('profile.show', $person) }}" class="truncate text-[15px] font-semibold hover:underline">{{ $person->full_name }}</a>
                                    <span class="chip shrink-0">{{ $roleLabel }}</span>
                                </div>
                                <p class="truncate text-sm text-ink-600 dark:text-ink-300">{{ $title }}</p>
                                <p class="truncate text-xs text-ink-400">{{ $department }}</p>
                            </div>
                        </div>

                        @if ($special)
                            <div class="mt-3 inline-flex w-fit items-center gap-1.5 rounded-lg bg-primary-500/10 px-2.5 py-1 text-xs font-semibold text-primary-700 dark:text-primary-300">
                                <x-ui-icon name="award" class="h-3.5 w-3.5" />{{ $special }}
                            </div>
                        @endif

                        <p class="mt-3 flex-1 text-sm text-ink-600 dark:text-ink-300">{{ $bio ?: 'No public bio added yet.' }}</p>

                        @if ($skills)
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($skills as $skill)
                                    <span class="chip">{{ $skill }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2 border-t border-ink-100 dark:border-ink-800/70 pt-4">
                            <a href="{{ route('profile.show', $person) }}" class="btn-secondary !px-3 !py-1.5 text-xs">View Profile</a>

                            @if (\App\Services\SocialRules::canMessage(auth()->user(), $person))
                                <a href="{{ route('messages.show', $person) }}" class="btn-secondary !px-3 !py-1.5 text-xs">Message</a>
                            @endif

                            @if ($friendship && $friendship->status === 'accepted')
                                <form method="POST" action="{{ route('network.remove', $person) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Unfriend</button>
                                </form>
                            @elseif ($friendship && $friendship->status === 'pending' && $friendship->requester_id === auth()->id())
                                <span class="chip">Request sent</span>
                            @elseif ($friendship && $friendship->status === 'pending' && $friendship->addressee_id === auth()->id())
                                <form method="POST" action="{{ route('network.accept', $person) }}">@csrf<button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Accept</button></form>
                                <form method="POST" action="{{ route('network.decline', $person) }}">@csrf<button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Decline</button></form>
                            @else
                                @if (auth()->user()->role === 'student' && in_array($person->role, ['faculty', 'official']))
                                    <a href="{{ route('connect-requests.index', ['target_id' => $person->id]) }}" class="btn-primary !px-3 !py-1.5 text-xs">Request permission to connect</a>
                                @else
                                    @php [$allowed] = \App\Services\SocialRules::canRequestConnection(auth()->user(), $person); @endphp
                                    @if ($allowed)
                                        <form method="POST" action="{{ route('network.request', $person) }}">
                                            @csrf
                                            <button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Connect</button>
                                        </form>
                                    @endif
                                @endif
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-6">{{ $people->links() }}</div>
        @endif
    </div>
</x-app-layout>
