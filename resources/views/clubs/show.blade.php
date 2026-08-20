@php $isMember = $club->members->contains(auth()->id()); @endphp
<x-app-layout :title="$club->name.' — UIU CampusConnect'">
    <div class="mx-auto max-w-3xl"
         x-data="{
            member: {{ $isMember ? 'true' : 'false' }},
            count: {{ $club->members->count() }},
            busy: false,
            async toggleMembership() {
                if (this.busy) return;
                this.busy = true;
                const url = this.member ? '{{ route('clubs.leave', $club) }}' : '{{ route('clubs.join', $club) }}';
                const data = await window.ccPost(url);
                this.busy = false;
                if (data.error) { window.ccToast('Something went wrong.', 'error'); return; }
                this.member = data.active;
                this.count = data.count;
                if (data.message) window.ccToast(data.message, 'success');
            },
         }">
        <div class="surface overflow-hidden">
            <div class="h-36 bg-gradient-to-br from-primary-400 to-primary-600" @if($club->cover_path) style="background-image:url({{ Storage::url($club->cover_path) }});background-size:cover" @endif></div>
            <div class="p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-xl font-semibold">{{ $club->name }}</h1>
                        <p class="text-sm text-ink-500 dark:text-ink-400">{{ $club->category }} · <span x-text="count"></span> members</p>
                    </div>
                    <button type="button" @click="toggleMembership()" :disabled="busy" :class="member ? 'btn-secondary' : 'btn-primary'" x-text="member ? 'Leave' : 'Join'"></button>
                </div>
                <p class="mt-4 text-[15px] leading-relaxed">{{ $club->description }}</p>
                @if ($club->meeting_time)<p class="mt-3 text-sm text-ink-500 dark:text-ink-400">{{ $club->meeting_time }} · {{ $club->meeting_place }}</p>@endif
            </div>
        </div>

        @if ($club->events->isNotEmpty())
            <div class="mt-5 space-y-3">
                @foreach ($club->events as $event)
                    <a href="{{ route('events.show', $event) }}" class="surface surface-hover flex items-center gap-4 p-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-300"><x-ui-icon name="calendar" class="h-5 w-5" /></span>
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $event->title }}</p>
                            <p class="text-sm text-ink-500 dark:text-ink-400">{{ $event->start_datetime->format('M j, g:i A') }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="empty-state mt-5">
                <span class="empty-icon"><x-ui-icon name="calendar" class="h-6 w-6" /></span>
                <h3>No events yet</h3>
                <p>When {{ $club->name }} schedules something, it will show up here.</p>
            </div>
        @endif
    </div>
</x-app-layout>
