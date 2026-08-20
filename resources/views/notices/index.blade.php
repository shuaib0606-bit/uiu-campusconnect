<x-app-layout title="Notices — UIU CampusConnect">
    <div class="mx-auto max-w-2xl" x-data="{ open: false }">
        <div class="section-heading mb-5">
            <div>
                <h1>Notices</h1>
                <p class="section-sub">Official announcements from your university</p>
            </div>
            @if ($canIssue)
                <button type="button" class="btn-primary" @click="open = true"><x-ui-icon name="plus" class="h-4 w-4" />Publish notice</button>
            @endif
        </div>

        @if ($canIssue)
            <div x-show="open" x-cloak class="surface mb-6 p-6">
                <form action="{{ route('notices.store') }}" method="POST" class="grid gap-3">
                    @csrf
                    <input name="title" placeholder="Title" required class="field">
                    <textarea name="body" placeholder="Notice" required rows="4" class="field"></textarea>
                    <div class="grid grid-cols-3 gap-3">
                        <input name="notice_type" placeholder="Type" required class="field">
                        <select name="priority" class="field"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select>
                        <input name="audience" placeholder="Audience" required value="all" class="field">
                    </div>
                    <button type="submit" class="btn-primary">Publish</button>
                </form>
            </div>
        @endif

        @if ($notices->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="flag" class="h-6 w-6" /></span>
                <h3>No notices yet</h3>
                <p>Registrar and administrative announcements will be posted here.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($notices as $notice)
                    <div class="surface surface-hover p-5">
                        <div class="flex items-center justify-between">
                            <span class="chip {{ $notice->priority === 'urgent' ? 'text-red-600' : '' }}">{{ ucfirst($notice->notice_type) }}</span>
                            <span class="text-xs text-ink-400">{{ $notice->created_at->format('M j, Y') }}</span>
                        </div>
                        <h3 class="mt-2 font-semibold">{{ $notice->title }}</h3>
                        <p class="mt-1 text-[15px] leading-relaxed">{{ $notice->body }}</p>
                        <p class="mt-3 text-xs text-ink-400">{{ $notice->issuer->full_name }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-6">{{ $notices->links() }}</div>
        @endif
    </div>
</x-app-layout>
