<x-app-layout :title="$otherUser->full_name.' - Messages'">
    <div class="mx-auto flex h-[calc(100vh-160px)] max-w-2xl flex-col surface overflow-hidden">
        <div class="flex items-center gap-3 border-b border-ink-100 dark:border-ink-800/70 px-5 py-3.5">
            <a href="{{ route('messages.index') }}" class="btn-icon rotate-180"><x-ui-icon name="chevron-right" class="h-4 w-4" /></a>
            <a href="{{ route('profile.show', $otherUser) }}" class="flex items-center gap-3">
                <x-user-avatar :user="$otherUser" size="h-9 w-9" text="text-xs" />
                <span class="text-sm font-semibold">{{ $otherUser->full_name }}</span>
            </a>
        </div>

        <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
            @foreach ($messages as $m)
                <div class="flex {{ $m->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[75%] rounded-2xl px-4 py-2.5 text-[14px] {{ $m->sender_id === auth()->id() ? 'bg-primary-500 text-white' : 'bg-ink-900/[0.05] dark:bg-white/[0.07]' }}">
                        @if ($m->body)<p class="whitespace-pre-line">{{ $m->body }}</p>@endif
                        @if ($m->attachment_path)<a href="{{ Storage::url($m->attachment_path) }}" target="_blank" class="mt-1 flex items-center gap-1.5 underline"><x-ui-icon name="paperclip" class="h-4 w-4" />Attachment</a>@endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($canMessage)
            <form action="{{ route('messages.store', $otherUser) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2 border-t border-ink-100 dark:border-ink-800/70 p-3">
                @csrf
                <label class="btn-icon cursor-pointer"><x-ui-icon name="paperclip" class="h-5 w-5" /><input type="file" name="attachment" class="hidden"></label>
                <input name="body" placeholder="Message" class="field py-2">
                <button type="submit" class="btn-icon"><x-ui-icon name="send" class="h-5 w-5" /></button>
            </form>
        @else
            <div class="border-t border-ink-100 dark:border-ink-800/70 p-4 text-center text-sm text-ink-400">Messaging unavailable</div>
        @endif
    </div>
</x-app-layout>
