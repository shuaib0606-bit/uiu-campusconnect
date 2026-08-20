@php
    $registered = $event->registrants->contains(auth()->id());
    $interested = $event->interested->contains(auth()->id());
@endphp
<x-app-layout :title="$event->title.' — UIU CampusConnect'">
    <div class="mx-auto max-w-2xl surface overflow-hidden"
         x-data="{
            registered: {{ $registered ? 'true' : 'false' }},
            interested: {{ $interested ? 'true' : 'false' }},
            checkedIn: false,
            count: {{ $event->registrants->count() }},
            busy: { register: false, interest: false, checkin: false },
            async act(kind, url) {
                if (this.busy[kind]) return;
                this.busy[kind] = true;
                const data = await window.ccPost(url);
                this.busy[kind] = false;
                if (data.error) { window.ccToast('Something went wrong.', 'error'); return; }
                if (kind === 'register') { this.registered = true; this.count = data.count; window.ccToast('You are registered.', 'success'); }
                if (kind === 'interest') { this.interested = data.active; }
                if (kind === 'checkin') { this.checkedIn = true; window.ccToast('Checked in.', 'success'); }
            },
         }">
        <div class="h-44 bg-gradient-to-br from-primary-400 to-primary-600" @if($event->poster_path) style="background-image:url({{ Storage::url($event->poster_path) }});background-size:cover" @endif></div>
        <div class="p-6">
            <p class="text-sm font-medium text-primary-600 dark:text-primary-300">{{ $event->start_datetime->format('l, M j · g:i A') }} – {{ $event->end_datetime->format('g:i A') }}</p>
            <h1 class="mt-1 text-xl font-semibold">{{ $event->title }}</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $event->location }} · <span x-text="count"></span>/{{ $event->capacity }} registered</p>
            <p class="mt-4 text-[15px] leading-relaxed">{{ $event->description }}</p>

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button" @click="act('register', '{{ route('events.register', $event) }}')" class="btn-primary" :disabled="registered || busy.register" x-text="registered ? 'Registered' : 'Register'"></button>
                <button type="button" @click="act('interest', '{{ route('events.interest', $event) }}')" class="btn-secondary font-medium" :class="interested ? 'text-primary-600 dark:text-primary-300' : ''">Interested</button>
                <button type="button" @click="act('checkin', '{{ route('events.checkin', $event) }}')" class="btn-secondary" :disabled="checkedIn" x-text="checkedIn ? 'Checked in' : 'Check in'"></button>
            </div>
        </div>
    </div>
</x-app-layout>
