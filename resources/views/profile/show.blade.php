@php $p = $profileUser->profile; @endphp
<x-app-layout :title="$profileUser->full_name.' — UIU CampusConnect'">
    <div class="mx-auto max-w-2xl">
        <div class="surface overflow-hidden">
            <div class="h-40 bg-gradient-to-br from-primary-400 to-primary-600 {{ $p?->cover_photo_path ? '' : '' }}" @if($p?->cover_photo_path) style="background-image:url({{ Storage::url($p->cover_photo_path) }});background-size:cover;background-position:center" @endif></div>
            <div class="px-6 pb-6">
                <div class="-mt-10 flex items-end justify-between">
                    <span class="avatar-ring grid h-20 w-20 place-items-center rounded-full bg-primary-100 text-primary-700 text-2xl font-semibold dark:bg-primary-900/50 dark:text-primary-200" @if($p?->profile_photo_path) style="background-image:url({{ Storage::url($p->profile_photo_path) }});background-size:cover" @endif>
                        @unless($p?->profile_photo_path) {{ $profileUser->avatar_initials }} @endunless
                    </span>

                    <div class="flex items-center gap-2 pb-1">
                        @if ($profileUser->id === auth()->id())
                            <a href="{{ route('profile.edit') }}" class="btn-secondary">Edit profile</a>
                        @else
                            @if ($canMessage)
                                <a href="{{ route('messages.show', $profileUser) }}" class="btn-secondary"><x-ui-icon name="message" class="h-4 w-4" />Message</a>
                            @endif
                            <div x-data="{
                                    status: '{{ $friendship ? $friendship->status : 'none' }}',
                                    busy: false,
                                    open: false,
                                    async connect() {
                                        if (this.busy) return;
                                        this.busy = true;
                                        const data = await window.ccPost('{{ route('network.request', $profileUser) }}');
                                        this.busy = false;
                                        if (data.error) { window.ccToast('Could not send request.', 'error'); return; }
                                        this.status = data.status;
                                        window.ccToast(data.status === 'accepted' ? 'Connected.' : 'Request sent.', 'success');
                                    },
                                    async block() {
                                        this.open = false;
                                        const data = await window.ccPost('{{ route('network.block', $profileUser) }}');
                                        if (data.error) { window.ccToast('Could not block this user.', 'error'); return; }
                                        window.ccToast('{{ $profileUser->full_name }} has been blocked.', 'success');
                                    },
                                 }" class="flex items-center gap-2">
                                <template x-if="status === 'none'">
                                    <button type="button" @click="connect()" class="btn-primary" :disabled="busy"><x-ui-icon name="user-plus" class="h-4 w-4" />Connect</button>
                                </template>
                                <template x-if="status === 'accepted'"><span class="chip">Connected</span></template>
                                <template x-if="status === 'pending'"><span class="chip">Pending</span></template>

                                <div class="relative">
                                    <button type="button" class="btn-icon" @click="open = !open" @click.outside="open = false"><x-ui-icon name="dots" class="h-5 w-5" /></button>
                                    <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-xl border border-ink-100 dark:border-ink-800 bg-white dark:bg-[#1a1815] py-1 shadow-lifted text-sm">
                                        <button type="button" @click="block()" class="flex w-full items-center gap-2 px-4 py-2 hover:bg-ink-900/5 dark:hover:bg-white/5"><x-ui-icon name="x" class="h-4 w-4" />Block</button>
                                        <form action="{{ route('profile.report', $profileUser) }}" method="POST" @submit.prevent="const r = prompt('Reason'); if(r){ $el.reason.value = r; $el.submit(); }"><input type="hidden" name="reason" x-ref="reason"><button class="flex w-full items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30">@csrf<x-ui-icon name="flag" class="h-4 w-4" />Report</button></form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <h1 class="mt-3 text-xl font-semibold">{{ $profileUser->full_name }}</h1>
                <p class="text-sm text-ink-500 dark:text-ink-400">{{ $profileUser->roleLabel() }}{{ $profileUser->department ? ' · '.$profileUser->department : '' }}</p>
                @if ($p?->bio)<p class="mt-3 text-[15px]">{{ $p->bio }}</p>@endif

                <div class="mt-4 flex flex-wrap gap-4 text-sm text-ink-500 dark:text-ink-400">
                    @if ($p?->show_email)<span class="flex items-center gap-1.5">{{ $profileUser->email }}</span>@endif
                    @if ($p?->room)<span class="flex items-center gap-1.5"><x-ui-icon name="map-pin" class="h-4 w-4" />{{ $p->room }}</span>@endif
                </div>
            </div>
        </div>

        <div class="mt-5 grid gap-5">
            @forelse ($posts as $post)
                @include('feed._post', ['post' => $post])
            @empty
                <div class="surface flex flex-col items-center gap-2 py-16 text-center">
                    <p class="text-sm font-medium text-ink-500 dark:text-ink-400">No posts yet</p>
                </div>
            @endforelse
            {{ $posts->links() }}
        </div>
    </div>
</x-app-layout>
