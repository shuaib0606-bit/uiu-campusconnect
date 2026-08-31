@php
    $p = $profileUser->profile;
    $subtitle = trim($profileUser->designation ?: $profileUser->roleLabel());
    if ($profileUser->department) { $subtitle .= ($subtitle ? ' · ' : '').$profileUser->department; }
    $tabUrl = fn (string $key) => route('profile.show', [$profileUser, 'tab' => $key]);
    $canSeeEmail = $isOwn || ! $p || $p->show_email;
    $canSeeStudentId = $isOwn || ! $p || $p->show_student_id;
    $canSeePhone = $isOwn || ($p && $p->show_phone);
    $canSeeFriends = $isOwn || ! $p || $p->show_friend_list;
    [$canRequestNow] = $canRequest;
    $statChips = [
        [$friendCount, 'friends'],
        [$followerCount, 'followers'],
        [$postCount, 'posts'],
        [$photoCount, 'photos'],
    ];
@endphp
<x-app-layout :title="$profileUser->full_name.' - UIU CampusConnect'">
    <div class="mx-auto max-w-[1180px]">
        <div class="mb-6 overflow-hidden rounded-b-2xl border border-ink-100 bg-white shadow-[0_16px_36px_rgba(15,23,42,0.08)] dark:border-ink-800/70 dark:bg-[#141210] dark:shadow-[0_16px_36px_rgba(0,0,0,0.28)]">
            {{-- Cover --}}
            <div class="relative h-[190px] bg-cover bg-center bg-gradient-to-br from-primary-500 to-primary-800 sm:h-[240px] lg:h-[300px]" @if($p?->cover_photo_path) style="background-image:url({{ Storage::url($p->cover_photo_path) }})" @endif>
                <div class="absolute inset-0 bg-gradient-to-b from-black/5 to-black/35"></div>
                @if ($isOwn)
                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" x-data>
                        @csrf @method('PUT')
                        <input type="hidden" name="full_name" value="{{ $profileUser->full_name }}">
                        <input type="hidden" name="department" value="{{ $profileUser->department }}">
                        <input type="hidden" name="designation" value="{{ $profileUser->designation }}">
                        <input id="cc-cover-input" type="file" name="cover" accept="image/*" class="hidden" @change="$el.form.submit()">
                        <label for="cc-cover-input" class="absolute bottom-4 right-4 z-[2] inline-flex cursor-pointer items-center gap-2 rounded-lg bg-white/95 px-3.5 py-2.5 text-sm font-semibold text-ink-900 shadow-lg hover:bg-white"><x-ui-icon name="camera" class="h-4 w-4" />Edit cover photo</label>
                    </form>
                @endif
            </div>

            {{-- Headline --}}
            <div class="grid grid-cols-1 items-end gap-4 border-b border-ink-100 px-4 pb-4 dark:border-ink-800/70 sm:px-6 sm:pb-5 lg:grid-cols-[auto_1fr_auto] lg:gap-6">
                <div class="relative z-[3] -mt-[72px] h-[144px] w-[144px] justify-self-center lg:-mt-[88px] lg:h-[176px] lg:w-[176px] lg:justify-self-auto">
                    <span class="grid h-full w-full place-items-center rounded-full border-[5px] border-white bg-gradient-to-br from-ink-500 to-primary-500 bg-cover bg-center text-[2.6rem] font-black text-white shadow-[0_14px_36px_rgba(15,23,42,0.22)] dark:border-[#141210] lg:border-[6px] lg:text-[3.5rem]" @if($p?->profile_photo_path) style="background-image:url({{ Storage::url($p->profile_photo_path) }})" @endif>
                        @unless($p?->profile_photo_path) {{ $profileUser->avatar_initials }} @endunless
                    </span>
                    @if ($isOwn)
                        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" x-data>
                            @csrf @method('PUT')
                            <input type="hidden" name="full_name" value="{{ $profileUser->full_name }}">
                            <input type="hidden" name="department" value="{{ $profileUser->department }}">
                            <input type="hidden" name="designation" value="{{ $profileUser->designation }}">
                            <input id="cc-avatar-input" type="file" name="avatar" accept="image/*" class="hidden" @change="$el.form.submit()">
                            <label for="cc-avatar-input" class="absolute bottom-1.5 right-1.5 grid h-11 w-11 cursor-pointer place-items-center rounded-full border-2 border-white bg-ink-50 text-ink-900 shadow-lg hover:bg-white dark:border-[#141210] dark:bg-[#1f1c19] dark:text-white" aria-label="Edit profile photo"><x-ui-icon name="camera" class="h-5 w-5" /></label>
                        </form>
                    @endif
                </div>

                <div class="min-w-0 pt-2 text-center lg:pt-4 lg:text-left">
                    <h1 class="text-[1.85rem] font-black leading-tight tracking-tight text-ink-900 dark:text-white lg:text-[2.25rem]">{{ $profileUser->full_name }}</h1>
                    <p class="mt-1 font-semibold text-ink-500 dark:text-ink-400">{{ $subtitle }}</p>
                    <div class="mt-1.5 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 font-bold text-ink-500 dark:text-ink-400 lg:justify-start">
                        @foreach ($statChips as $i => [$count, $label])
                            @if ($i > 0)<span class="text-ink-300 dark:text-ink-600">&middot;</span>@endif
                            <span>{{ $count }} {{ $label }}</span>
                        @endforeach
                    </div>
                    @if ($p?->bio)<p class="mx-auto mt-2 max-w-xl font-semibold text-ink-900 dark:text-white lg:mx-0">{{ \Illuminate\Support\Str::limit($p->bio, 160) }}</p>@endif
                    <div class="mt-2.5 flex flex-wrap items-center justify-center gap-1.5 lg:justify-start">
                        <span class="chip">{{ $profileUser->roleLabel() }}</span>
                        @foreach ($specialRoles as $role)
                            <span class="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">{{ $role }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2 pb-0.5 lg:justify-end lg:self-end">
                    @if ($isOwn)
                        <a href="{{ route('profile.edit') }}" class="btn-primary"><x-ui-icon name="edit" class="h-4 w-4" />Edit profile</a>
                        <a href="{{ route('feed.index') }}" class="btn-secondary">Create post</a>
                        <a href="{{ route('settings.password') }}" class="btn-secondary"><x-ui-icon name="shield" class="h-4 w-4" />Account settings</a>
                    @elseif (auth()->check())
                        @if ($friendship && $friendship->status === 'accepted')
                            <span class="btn-secondary cursor-default"><x-ui-icon name="check-circle" class="h-4 w-4" />Friends</span>
                            <form method="POST" action="{{ route('network.remove', $profileUser) }}"><button type="submit" class="btn-secondary">Unfriend</button></form>
                        @elseif ($friendship && $friendship->status === 'pending' && $friendship->addressee_id === auth()->id())
                            <form method="POST" action="{{ route('network.accept', $profileUser) }}"><button type="submit" class="btn-primary">Accept request</button></form>
                            <form method="POST" action="{{ route('network.decline', $profileUser) }}"><button type="submit" class="btn-secondary">Decline</button></form>
                        @elseif ($friendship && $friendship->status === 'pending')
                            <span class="btn-secondary cursor-default">Request sent</span>
                            <form method="POST" action="{{ route('network.remove', $profileUser) }}"><button type="submit" class="btn-secondary">Cancel</button></form>
                        @elseif (auth()->user()->role === 'student' && in_array($profileUser->role, ['faculty', 'official'], true))
                            <a href="{{ route('connect-requests.index', ['target_id' => $profileUser->id]) }}" class="btn-primary">Request permission to connect</a>
                        @elseif ($canRequestNow)
                            <form method="POST" action="{{ route('network.request', $profileUser) }}"><button type="submit" class="btn-primary"><x-ui-icon name="user-plus" class="h-4 w-4" />Add friend</button></form>
                        @endif

                        <form method="POST" action="{{ route('network.follow', $profileUser) }}"><button type="submit" class="btn-secondary">{{ $isFollowing ? 'Following' : 'Follow' }}</button></form>
                        @if ($canMessage)
                            <a href="{{ route('messages.index', ['with' => $profileUser->id]) }}" class="btn-secondary"><x-ui-icon name="message" class="h-4 w-4" />Message</a>
                        @endif

                        <div class="relative" x-data="{ open: false }">
                            <button type="button" class="btn-icon !h-11 !w-11 border border-ink-100 dark:border-ink-800/70" @click="open = !open" @click.outside="open = false"><x-ui-icon name="dots" class="h-5 w-5" /></button>
                            <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-xl border border-ink-100 py-1 text-sm shadow-lifted dark:border-ink-800 bg-[var(--surface-raised)]">
                                <form method="POST" action="{{ route('network.block', $profileUser) }}"><button type="submit" class="flex w-full items-center gap-2 px-4 py-2 hover:bg-ink-900/5 dark:hover:bg-white/5"><x-ui-icon name="x" class="h-4 w-4" />Block</button></form>
                                <form method="POST" action="{{ route('profile.report', $profileUser) }}" x-data @submit="if(!confirm('Report this profile?')) $event.preventDefault();"><input type="hidden" name="reason" value="Reported from profile"><button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"><x-ui-icon name="flag" class="h-4 w-4" />Report</button></form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary">Sign in to connect</a>
                    @endif
                </div>
            </div>

            {{-- Tabs --}}
            <nav class="flex gap-1 overflow-x-auto px-4 sm:px-6">
                @foreach (['all' => 'All', 'about' => 'About', 'friends' => 'Friends', 'photos' => 'Photos', 'posts' => 'Posts', 'more' => 'More'] as $key => $label)
                    <a href="{{ $tabUrl($key) }}" class="flex h-[52px] shrink-0 items-center border-b-[3px] px-3.5 text-[15px] font-bold {{ $tab === $key ? 'border-primary-500 text-primary-600 dark:text-primary-300' : 'border-transparent text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-white' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        <div class="grid gap-5 {{ in_array($tab, ['all', 'about', 'friends', 'more'], true) ? 'lg:grid-cols-[minmax(280px,380px)_minmax(0,1fr)]' : '' }}">
            @if (in_array($tab, ['all', 'about', 'friends', 'more'], true))
                <aside class="space-y-4">
                    @if (in_array($tab, ['all', 'about'], true))
                        <div class="surface p-5">
                            <div class="mb-3 flex items-center justify-between"><h2 class="text-lg font-black">Intro</h2>@if($isOwn)<a href="{{ route('profile.edit') }}" class="text-sm font-bold text-primary-600 hover:underline dark:text-primary-300">Edit</a>@endif</div>
                            @if ($p?->bio)<p class="mb-3 whitespace-pre-line text-center text-sm font-semibold leading-relaxed text-ink-700 dark:text-ink-200">{{ \Illuminate\Support\Str::limit($p->bio, 260) }}</p>@endif
                            <ul class="space-y-3.5">
                                @if ($profileUser->department)<li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm font-semibold leading-tight"><x-ui-icon name="users" class="mt-0.5 h-5 w-5 text-ink-400" /><span>{{ $profileUser->department }}</span></li>@endif
                                @if ($profileUser->designation)<li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm font-semibold leading-tight"><x-ui-icon name="user" class="mt-0.5 h-5 w-5 text-ink-400" /><span>{{ $profileUser->designation }}</span></li>@endif
                                @if ($canSeeStudentId && ($profileUser->student_id || $profileUser->employee_id))<li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm font-semibold leading-tight"><x-ui-icon name="shield" class="mt-0.5 h-5 w-5 text-ink-400" /><span>{{ $profileUser->student_id ?: $profileUser->employee_id }}</span></li>@endif
                                @if ($p?->room)<li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm font-semibold leading-tight"><x-ui-icon name="map-pin" class="mt-0.5 h-5 w-5 text-ink-400" /><span>{{ $p->room }}</span></li>@endif
                                @if ($canSeePhone && $p?->phone)<li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm font-semibold leading-tight"><x-ui-icon name="message" class="mt-0.5 h-5 w-5 text-ink-400" /><span>{{ $p->phone }}</span></li>@endif
                                @if ($canSeeEmail)<li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm font-semibold leading-tight"><x-ui-icon name="mail" class="mt-0.5 h-5 w-5 text-ink-400" /><span>{{ $profileUser->email }}</span></li>@endif
                                <li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm font-semibold leading-tight"><x-ui-icon name="calendar" class="mt-0.5 h-5 w-5 text-ink-400" /><span>Joined {{ $profileUser->created_at->format('M Y') }}</span></li>
                            </ul>
                        </div>
                    @endif

                    @if (in_array($tab, ['all', 'about'], true) && ($profileUser->role === 'faculty' || $p?->office_hours || $p?->research_interests || $p?->courses_taught || $p?->academic_resources))
                        <div class="surface p-5">
                            <h2 class="mb-3 text-lg font-black">Faculty communication</h2>
                            <ul class="space-y-3.5">
                                @if ($p?->office_hours)<li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm leading-tight"><x-ui-icon name="calendar" class="mt-0.5 h-5 w-5 text-ink-400" /><span><strong class="font-bold">Office hours</strong><br>{{ $p->office_hours }}</span></li>@endif
                                @if ($p?->research_interests)<li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm leading-tight"><x-ui-icon name="lightbulb" class="mt-0.5 h-5 w-5 text-ink-400" /><span><strong class="font-bold">Research</strong><br>{{ $p->research_interests }}</span></li>@endif
                                @if ($p?->courses_taught)<li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm leading-tight"><x-ui-icon name="file-text" class="mt-0.5 h-5 w-5 text-ink-400" /><span><strong class="font-bold">Courses</strong><br>{{ $p->courses_taught }}</span></li>@endif
                                @if ($p?->academic_resources)<li class="grid grid-cols-[28px_1fr] items-start gap-3 text-sm leading-tight"><x-ui-icon name="file-text" class="mt-0.5 h-5 w-5 text-ink-400" /><span><strong class="font-bold">Resources</strong><br>{{ \Illuminate\Support\Str::limit($p->academic_resources, 300) }}</span></li>@endif
                            </ul>
                        </div>
                    @endif

                    @if (in_array($tab, ['all', 'friends'], true))
                        <div class="surface p-5">
                            <div class="mb-3 flex items-center justify-between"><h2 class="text-lg font-black">Friends</h2><span class="font-bold text-ink-400">{{ $friendCount }}</span></div>
                            @if (! $canSeeFriends)
                                <p class="text-sm font-semibold text-ink-400">This user has hidden their friend list.</p>
                            @elseif ($friends->isEmpty())
                                <p class="text-sm font-semibold text-ink-400">No friends to show yet.</p>
                            @else
                                <div class="grid grid-cols-3 gap-2.5">
                                    @foreach ($friends as $friend)
                                        <a href="{{ route('profile.show', $friend->id) }}" class="text-sm font-bold leading-tight text-ink-900 dark:text-white">
                                            <span class="mb-1 grid aspect-square w-full place-items-center rounded-xl bg-cover bg-center bg-gradient-to-br from-ink-500 to-primary-500 text-lg font-black text-white" @if($friend->profile_photo_path) style="background-image:url({{ Storage::url($friend->profile_photo_path) }})" @endif>
                                                @unless($friend->profile_photo_path){{ $friend->avatar_initials }}@endunless
                                            </span>
                                            <span class="block truncate">{{ $friend->full_name }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if (in_array($tab, ['all', 'more'], true))
                        <div class="surface p-5">
                            <h2 class="mb-3 text-lg font-black">Clubs &amp; interests</h2>
                            @if ($clubs->isNotEmpty())
                                <div class="mb-3 flex flex-wrap gap-1.5">
                                    @foreach ($clubs->take(8) as $club)
                                        <a href="{{ route('clubs.show', $club->slug) }}" class="chip !bg-blue-500/10 !text-blue-700 dark:!text-blue-300">{{ $club->name }}{{ $club->club_role && strtolower($club->club_role) !== 'member' ? ' · '.ucwords($club->club_role) : '' }}</a>
                                    @endforeach
                                </div>
                            @endif
                            @php $tags = collect(explode(',', $p?->skills ?? ''))->merge(explode(',', $p?->interests ?? ''))->map(fn($t) => trim($t))->filter()->unique()->take(10); @endphp
                            @if ($tags->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($tags as $tag)<span class="chip">{{ $tag }}</span>@endforeach
                                </div>
                            @else
                                <p class="text-sm font-semibold text-ink-400">No interests added yet.</p>
                            @endif
                        </div>
                    @endif
                </aside>
            @endif

            @if (in_array($tab, ['all', 'posts', 'photos'], true))
                <section class="{{ in_array($tab, ['posts', 'photos'], true) ? 'mx-auto w-full max-w-[880px]' : '' }} space-y-4">
                    @if ($tab === 'all' && $isOwn)
                        <div class="surface p-4">
                            <a href="{{ route('feed.index') }}" class="flex items-center gap-3">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gradient-to-br from-ink-500 to-primary-500 text-sm font-black text-white">{{ $profileUser->avatar_initials }}</span>
                                <span class="flex-1 rounded-full bg-ink-900/[0.04] px-4 py-3 text-sm font-semibold text-ink-500 dark:bg-white/[0.05] dark:text-ink-400">What's on your mind?</span>
                            </a>
                            <div class="mt-3 grid grid-cols-3 gap-2 border-t border-ink-100 pt-3 dark:border-ink-800/70">
                                <a href="{{ route('feed.index') }}" class="flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-bold text-ink-500 hover:bg-ink-900/[0.04] hover:text-ink-900 dark:text-ink-400 dark:hover:bg-white/[0.05] dark:hover:text-white"><x-ui-icon name="image" class="h-5 w-5" />Photo/video</a>
                                <a href="{{ route('feed.index') }}" class="flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-bold text-ink-500 hover:bg-ink-900/[0.04] hover:text-ink-900 dark:text-ink-400 dark:hover:bg-white/[0.05] dark:hover:text-white"><x-ui-icon name="file-text" class="h-5 w-5" />Document</a>
                                <a href="{{ route('feed.index') }}" class="flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-bold text-ink-500 hover:bg-ink-900/[0.04] hover:text-ink-900 dark:text-ink-400 dark:hover:bg-white/[0.05] dark:hover:text-white"><x-ui-icon name="award" class="h-5 w-5" />Life update</a>
                            </div>
                        </div>
                    @endif

                    @if ($tab === 'photos')
                        <div class="surface flex items-center justify-between p-5"><h2 class="text-lg font-black">Photos</h2><span class="font-bold text-ink-400">{{ $photoCount }} photos</span></div>
                        <div class="surface p-5">
                            @if ($photos->isEmpty() && ! $p?->profile_photo_path && ! $p?->cover_photo_path)
                                <p class="text-sm font-semibold text-ink-400">No photos to show yet.</p>
                            @else
                                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                                    @if ($p?->profile_photo_path)<a href="{{ Storage::url($p->profile_photo_path) }}" target="_blank" class="aspect-square overflow-hidden rounded-2xl border border-ink-100 bg-cover bg-center dark:border-ink-800/70" style="background-image:url('{{ Storage::url($p->profile_photo_path) }}')"></a>@endif
                                    @if ($p?->cover_photo_path)<a href="{{ Storage::url($p->cover_photo_path) }}" target="_blank" class="aspect-square overflow-hidden rounded-2xl border border-ink-100 bg-cover bg-center dark:border-ink-800/70" style="background-image:url('{{ Storage::url($p->cover_photo_path) }}')"></a>@endif
                                    @foreach ($photos as $photo)
                                        <a href="{{ Storage::url($photo->media_path) }}" target="_blank" class="aspect-square overflow-hidden rounded-2xl border border-ink-100 bg-cover bg-center dark:border-ink-800/70" style="background-image:url('{{ Storage::url($photo->media_path) }}')"></a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        @if (in_array($tab, ['all', 'posts'], true))
                            <div class="surface flex items-center justify-between p-5">
                                <h2 class="text-lg font-black">Posts</h2>
                                @if ($isOwn)<a href="{{ route('feed.index') }}" class="btn-secondary !px-3 !py-1.5 text-xs">Create post</a>@endif
                            </div>
                            @forelse ($posts as $post)
                                @include('feed._post', ['post' => $post])
                            @empty
                                <div class="surface flex flex-col items-center gap-2 py-16 text-center">
                                    <p class="text-sm font-semibold text-ink-500 dark:text-ink-400">No posts to show yet</p>
                                </div>
                            @endforelse
                            {{ $posts->links() }}
                        @endif
                    @endif
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
