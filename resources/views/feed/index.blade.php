<x-app-layout title="Feed — UIU CampusConnect">
    <div class="mx-auto grid max-w-5xl gap-5 lg:grid-cols-[1fr_300px] lg:items-start">
    <div class="grid max-w-2xl gap-5">
        @if ($latestNotice)
            <a href="{{ route('notices.index') }}" class="surface flex items-center gap-3 px-5 py-3.5 hover:border-primary-300 transition">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-300"><x-ui-icon name="flag" class="h-4 w-4" /></span>
                <span class="truncate text-sm font-medium">{{ $latestNotice->title }}</span>
                <x-ui-icon name="chevron-right" class="ml-auto h-4 w-4 text-ink-400 shrink-0" />
            </a>
        @endif

        <div class="surface p-5">
            <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="flex gap-3" x-data="{ open: false, poll: false, options: ['',''] }">
                @csrf
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700 text-sm font-semibold dark:bg-primary-900/40 dark:text-primary-200">{{ auth()->user()->avatar_initials }}</span>
                <div class="flex-1">
                    <textarea name="body" rows="2" x-on:focus="open = true" required placeholder="Share something with campus" class="field resize-none border-transparent bg-ink-900/[0.03] dark:bg-white/[0.04] focus:bg-white dark:focus:bg-[#181614]"></textarea>

                    <div x-show="open" x-cloak class="mt-3 space-y-3">
                        <template x-if="poll">
                            <div class="space-y-2">
                                <template x-for="(opt, i) in options" :key="i">
                                    <input :name="'poll_options[' + i + ']'" x-model="options[i]" class="field" placeholder="Option">
                                </template>
                                <button type="button" class="btn-ghost text-xs" @click="options.push('')">Add option</button>
                            </div>
                        </template>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <label class="btn-icon cursor-pointer">
                                    <x-ui-icon name="image" class="h-5 w-5" />
                                    <input type="file" name="media" class="hidden" accept="image/*,video/*">
                                </label>
                                <button type="button" class="btn-icon" @click="poll = !poll"><x-ui-icon name="award" class="h-5 w-5" /></button>
                            </div>
                            <button type="submit" class="btn-primary">Post</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="surface-flat flex p-1">
            <a href="{{ route('feed.index', ['tab' => 'for-you']) }}" class="flex-1 rounded-xl px-4 py-2 text-center text-sm font-medium {{ $tab === 'for-you' ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300' }}">For You</a>
            <a href="{{ route('feed.index', ['tab' => 'following']) }}" class="flex-1 rounded-xl px-4 py-2 text-center text-sm font-medium {{ $tab === 'following' ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300' }}">Following</a>
        </div>

        @forelse ($posts as $post)
            @include('feed._post', ['post' => $post])
        @empty
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="home" class="h-6 w-6" /></span>
                <h3>{{ $tab === 'following' ? 'No posts from people you follow' : 'Nothing here yet' }}</h3>
                <p>{{ $tab === 'following' ? 'Follow more people to see their posts here.' : 'Be the first to share something with campus.' }}</p>
            </div>
        @endforelse

        {{ $posts->links() }}
    </div>

    <aside class="hidden lg:block lg:sticky lg:top-20 space-y-5">
        @if ($upcomingEvents->isNotEmpty())
            <div class="rail-card">
                <h3 class="mb-3">Upcoming events</h3>
                <div class="space-y-3">
                    @foreach ($upcomingEvents as $event)
                        <a href="{{ route('events.show', $event) }}" class="flex items-start gap-3 group">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-300"><x-ui-icon name="calendar" class="h-4 w-4" /></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium group-hover:underline">{{ $event->title }}</span>
                                <span class="block text-xs text-ink-500 dark:text-ink-400">{{ $event->start_datetime->format('M j, g:i A') }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('events.index') }}" class="mt-4 block text-center text-xs font-medium text-primary-600 dark:text-primary-300 hover:underline">View all events</a>
            </div>
        @endif

        @if ($suggestedClubs->isNotEmpty())
            <div class="rail-card">
                <h3 class="mb-3">Clubs for you</h3>
                <div class="space-y-3">
                    @foreach ($suggestedClubs as $club)
                        <a href="{{ route('clubs.show', $club) }}" class="flex items-center gap-3 group">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-300"><x-ui-icon name="award" class="h-4 w-4" /></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium group-hover:underline">{{ $club->name }}</span>
                                <span class="block text-xs text-ink-500 dark:text-ink-400">{{ $club->members_count }} member{{ $club->members_count === 1 ? '' : 's' }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('clubs.index') }}" class="mt-4 block text-center text-xs font-medium text-primary-600 dark:text-primary-300 hover:underline">Browse clubs</a>
            </div>
        @endif
    </aside>
    </div>
</x-app-layout>
