<x-app-layout title="Network — UIU CampusConnect">
    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_320px]">
        <div>
            <div class="section-heading mb-5">
                <div>
                    <h1>Network</h1>
                    <p class="section-sub">{{ $people->total() }} people at UIU CampusConnect</p>
                </div>
            </div>

            <form method="GET" class="relative mb-5">
                <x-ui-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Find people" class="field pl-10">
            </form>

            @if ($people->isEmpty())
                <div class="empty-state">
                    <span class="empty-icon"><x-ui-icon name="users" class="h-6 w-6" /></span>
                    <h3>No one matches "{{ $search }}"</h3>
                    <p>Try a different name, or clear your search to browse everyone on campus.</p>
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach ($people as $person)
                        <a href="{{ route('profile.show', $person) }}" class="surface surface-hover flex flex-col items-center gap-2 p-5 text-center">
                            <span class="avatar-ring grid h-14 w-14 place-items-center rounded-full bg-primary-100 text-primary-700 text-lg font-semibold dark:bg-primary-900/40 dark:text-primary-200">{{ $person->avatar_initials }}</span>
                            <span class="truncate text-sm font-medium w-full">{{ $person->full_name }}</span>
                            <span class="chip">{{ $person->roleLabel() }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="mt-6">{{ $people->links() }}</div>
            @endif
        </div>

        <aside class="space-y-5">
            <div class="surface p-5" x-data="{ pending: {{ $pendingIncoming->isNotEmpty() ? 'true' : 'false' }} }" x-show="pending" x-cloak>
                <h2 class="mb-3 text-sm font-semibold">Requests</h2>
                <div class="space-y-3">
                    @foreach ($pendingIncoming as $req)
                        <div x-data="{
                                gone: false,
                                async decide(url) {
                                    const data = await window.ccPost(url);
                                    if (data.error) { window.ccToast('Something went wrong.', 'error'); return; }
                                    this.gone = true;
                                },
                             }" x-show="!gone" x-transition.opacity class="flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-ink-900/5 dark:bg-white/10 text-xs font-semibold">{{ $req->requester->avatar_initials }}</span>
                            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $req->requester->full_name }}</span>
                            <button type="button" @click="decide('{{ route('network.accept', $req->requester) }}')" class="btn-icon"><x-ui-icon name="check" class="h-4 w-4" /></button>
                            <button type="button" @click="decide('{{ route('network.decline', $req->requester) }}')" class="btn-icon"><x-ui-icon name="x" class="h-4 w-4" /></button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="surface p-5">
                <h2 class="mb-3 text-sm font-semibold">Connections ({{ $connections->count() }})</h2>
                <div class="space-y-3">
                    @forelse ($connections as $c)
                        @php $other = $c->requester_id === auth()->id() ? $c->addressee : $c->requester; @endphp
                        <a href="{{ route('profile.show', $other) }}" class="flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-ink-900/5 dark:bg-white/10 text-xs font-semibold">{{ $other->avatar_initials }}</span>
                            <span class="truncate text-sm font-medium">{{ $other->full_name }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-ink-400">No connections yet</p>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
