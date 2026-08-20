@php $isMember = $group->members->contains(auth()->id()); @endphp
<x-app-layout :title="$group->name.' — UIU CampusConnect'">
    <div class="mx-auto max-w-3xl surface p-6"
         x-data="{
            member: {{ $isMember ? 'true' : 'false' }},
            count: {{ $group->members->count() }},
            busy: false,
            async join() {
                if (this.busy) return;
                this.busy = true;
                const data = await window.ccPost('{{ route('groups.join', $group) }}');
                this.busy = false;
                if (data.error) { window.ccToast('Something went wrong.', 'error'); return; }
                this.member = true;
                this.count = data.count;
                window.ccToast(data.message || 'Joined.', 'success');
            },
         }">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-xl font-semibold">{{ $group->name }}</h1>
                <p class="text-sm text-ink-500 dark:text-ink-400"><span x-text="count"></span> members</p>
            </div>
            <button type="button" @click="join()" x-show="!member" x-cloak :disabled="busy" class="btn-primary">Join</button>
            <span x-show="member" x-cloak class="chip">Member</span>
        </div>
        <p class="mt-4 text-[15px] leading-relaxed">{{ $group->description }}</p>

        @if ($group->files->isNotEmpty())
            <div class="mt-5 space-y-2">
                @foreach ($group->files as $file)
                    <a href="{{ Storage::url($file->file_path) }}" class="flex items-center gap-2 rounded-xl border border-ink-100 dark:border-ink-800 px-4 py-2.5 text-sm hover:border-primary-300 transition-colors"><x-ui-icon name="paperclip" class="h-4 w-4" />{{ $file->original_name }}</a>
                @endforeach
            </div>
        @else
            <div class="empty-state mt-5">
                <span class="empty-icon"><x-ui-icon name="paperclip" class="h-6 w-6" /></span>
                <h3>No files shared yet</h3>
                <p>Notes, slides, and resources shared with this group will appear here.</p>
            </div>
        @endif
    </div>
</x-app-layout>
