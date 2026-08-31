<x-app-layout :title="$group->name.' - UIU CampusConnect'">
    <div class="mx-auto max-w-4xl"
         x-data="{
            member: {{ $isMember ? 'true' : 'false' }},
            count: {{ $group->members()->count() }},
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
        <a href="{{ route('groups.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white"><x-ui-icon name="chevron-right" class="h-4 w-4 rotate-180" />Back to Groups</a>

        <div class="surface mb-5 p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <span class="chip">{{ ucfirst($group->group_type) }}</span>
                    <h1 class="mt-1 text-[22px] font-semibold tracking-tight">{{ $group->name }}</h1>
                    <p class="section-sub">{{ $group->department ?: 'All departments' }} &middot; Owner {{ $group->owner->full_name }}</p>
                </div>
                <div class="shrink-0">
                    <button type="button" @click="join()" x-show="!member" x-cloak :disabled="busy" class="btn-primary">Join Group</button>
                    <span x-show="member" x-cloak class="chip">Member</span>
                </div>
            </div>
            <p class="mt-4 whitespace-pre-line text-sm text-ink-600 dark:text-ink-300">{{ $group->description }}</p>
            <p class="section-sub mt-3"><span x-text="count"></span> members</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="surface p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Members</h2>
                <div class="space-y-3">
                    @forelse ($members as $member)
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">{{ $member->avatar_initials }}</span>
                            <div class="min-w-0">
                                <a href="{{ route('profile.show', $member) }}" class="text-sm font-semibold hover:underline">{{ $member->full_name }}</a>
                                <p class="section-sub truncate">{{ ucfirst($member->pivot->role ?? 'member') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-ink-400">No members yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="surface p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Class Resources / Files</h2>

                @if ($isMember)
                    <form method="POST" action="{{ route('groups.upload-file', $group) }}" enctype="multipart/form-data" class="mb-4 space-y-2">
                        @csrf
                        <input name="title" placeholder="File title" class="field">
                        <input type="file" name="group_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx" required class="field !py-1.5">
                        <button type="submit" class="btn-primary w-full">Upload Resource</button>
                    </form>
                @else
                    <p class="section-sub mb-4">Join the group to upload files.</p>
                @endif

                <div class="space-y-2">
                    @forelse ($files as $file)
                        @php
                            $bytes = (int) $file->size;
                            $sizeLabel = $bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : number_format(max($bytes, 1) / 1024, 1).' KB';
                        @endphp
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-ink-100 px-4 py-2.5 dark:border-ink-800">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $file->title ?: $file->original_name }}</p>
                                <p class="section-sub truncate">Uploaded by {{ $file->uploader->full_name }} &middot; {{ $sizeLabel }}</p>
                            </div>
                            <a href="{{ Storage::url($file->file_path) }}" download class="btn-secondary shrink-0 !px-3 !py-1.5 text-xs">Download</a>
                        </div>
                    @empty
                        <div class="empty-state">
                            <span class="empty-icon"><x-ui-icon name="paperclip" class="h-6 w-6" /></span>
                            <h3>No files shared yet</h3>
                            <p>Notes, slides, and resources shared with this group will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
