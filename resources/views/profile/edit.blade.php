@php
    $p = $user->profile;
    $showAcademic = in_array($user->role, ['faculty', 'official', 'admin'], true);
    $levels = \App\Support\Visibility::LABELS;
    // A small "who sees this" menu beside a field.
    $who = function (string $name) use ($p, $levels) {
        $current = $p?->{$name} ?: \App\Support\Visibility::EVERYONE;
        $options = collect($levels)->map(fn ($label, $value) => '<option value="'.$value.'"'.($value === $current ? ' selected' : '').'>'.e($label).'</option>')->implode('');

        return new \Illuminate\Support\HtmlString('<select name="'.$name.'" class="field w-auto !py-1 !text-xs" aria-label="Who can see this">'.$options.'</select>');
    };
@endphp
<x-app-layout title="Edit profile - UIU CampusConnect">
    <div class="mx-auto max-w-2xl">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-3 p-6">
            <div>
                <h1 class="text-[19px] font-semibold">Edit profile</h1>
                <p class="section-sub">Update the details that appear on your campus profile.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('profile.show', $user) }}" class="btn-secondary">View profile</a>
                <a href="{{ route('settings.password') }}" class="btn-secondary">Account settings</a>
            </div>
        </div>

        <div class="surface p-6">
            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf @method('PUT')

                <div class="flex items-center gap-4 rounded-xl bg-ink-900/[0.03] p-4 dark:bg-white/[0.04]">
                    <span class="avatar-ring grid h-16 w-16 shrink-0 place-items-center rounded-full bg-primary-100 text-xl font-semibold text-primary-700 dark:bg-primary-900/50 dark:text-primary-200" @if($p?->profile_photo_path) style="background-image:url({{ Storage::url($p->profile_photo_path) }});background-size:cover" @endif>
                        @unless($p?->profile_photo_path) {{ $user->avatar_initials }} @endunless
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $user->full_name }}</p>
                        <p class="section-sub truncate">{{ $user->designation ?: $user->roleLabel() }}{{ $user->department ? ' · '.$user->department : '' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div><label class="mb-1.5 block text-sm font-medium">Profile photo</label><input type="file" name="avatar" accept="image/*" class="field !py-1.5"></div>
                    <div><label class="mb-1.5 block text-sm font-medium">Cover photo</label><input type="file" name="cover" accept="image/*" class="field !py-1.5"></div>
                </div>

                <div><label class="mb-1.5 block text-sm font-medium">Full name</label><input name="full_name" value="{{ $user->full_name }}" required class="field"></div>

                <div class="grid grid-cols-2 gap-4">
                    <div><label class="mb-1.5 block text-sm font-medium">Department</label><input name="department" value="{{ $user->department }}" class="field"></div>
                    <div><label class="mb-1.5 block text-sm font-medium">Designation</label><input name="designation" value="{{ $user->designation }}" class="field"></div>
                </div>

                <div x-data="{ bio: @js(old('bio', $p?->bio ?? '')) }">
                    <div class="mb-1.5 flex items-baseline justify-between">
                        <label class="block text-sm font-medium">Bio</label>
                        <span class="text-xs" :class="bio.length > 300 ? 'text-red-600' : 'text-ink-400'" x-text="`${bio.length} / 300`"></span>
                    </div>
                    <textarea name="bio" rows="3" x-model="bio" placeholder="One or two lines about you" class="field"></textarea>
                    <p class="mt-1 text-xs text-ink-400">Shown at the top of your profile. @if ($showAcademic)Longer text - biography, education, publications - belongs in your <a href="{{ route('academic.edit') }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-300">academic profile</a>.@endif</p>
                    @error('bio')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2"><label class="block text-sm font-medium">Room / office</label>@if ($showAcademic){{ $who('room_visibility') }}@endif</div>
                        <input name="room" value="{{ $p?->room }}" placeholder="Room, office, or location" class="field">
                    </div>
                    <div><label class="mb-1.5 block text-sm font-medium">Phone</label><input name="phone" value="{{ $p?->phone }}" placeholder="Optional phone" class="field"></div>
                </div>

                <div><label class="mb-1.5 block text-sm font-medium">Skills</label><input name="skills" value="{{ $p?->skills }}" placeholder="AI, Python, UI design" class="field"></div>
                <div><label class="mb-1.5 block text-sm font-medium">Interests</label><input name="interests" value="{{ $p?->interests }}" placeholder="Research, debate, robotics" class="field"></div>

                @if ($showAcademic)
                    <div class="border-t border-ink-100 pt-4 dark:border-ink-800/70">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold">Academic / official details</h2>
                            @if (in_array($user->role, ['faculty', 'official'], true))
                                <a href="{{ route('academic.edit') }}" class="text-sm font-bold text-primary-600 hover:underline dark:text-primary-300">Edit education, publications &amp; more &rarr;</a>
                            @endif
                        </div>
                        <p class="mb-3 text-xs text-ink-400">Everyone = anyone who opens your profile. UIU members = signed-in students and staff. Only me = hidden.</p>
                        <div class="space-y-4">
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-2"><label class="block text-sm font-medium">PABX extension</label>{{ $who('pabx_visibility') }}</div>
                                <input name="pabx" value="{{ $p?->pabx }}" placeholder="3122" class="field">
                            </div>
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-2"><label class="block text-sm font-medium">Office hours</label>{{ $who('office_hours_visibility') }}</div>
                                <input name="office_hours" value="{{ $p?->office_hours }}" placeholder="Sun/Tue 2:00 PM - 4:00 PM" class="field">
                            </div>
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-2"><label class="block text-sm font-medium">Research interests</label>{{ $who('research_visibility') }}</div>
                                <input name="research_interests" value="{{ $p?->research_interests }}" placeholder="Machine learning, HCI, data science" class="field">
                                <p class="mt-1 text-xs text-ink-400">Separate with commas - they show as tags.</p>
                            </div>
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-2"><label class="block text-sm font-medium">Courses taught</label>{{ $who('courses_visibility') }}</div>
                                <input name="courses_taught" value="{{ $p?->courses_taught }}" placeholder="CSE101, CSE301" class="field">
                            </div>
                            <div><label class="mb-1.5 block text-sm font-medium">Academic resources</label><textarea name="academic_resources" rows="3" placeholder="Links or notes for students..." class="field">{{ $p?->academic_resources }}</textarea></div>
                        </div>
                    </div>
                @endif

                <div class="border-t border-ink-100 pt-4 dark:border-ink-800/70">
                    <h2 class="mb-3 text-sm font-semibold">Privacy</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_email" value="1" {{ (! $p || $p->show_email) ? 'checked' : '' }} class="h-4 w-4 rounded border-ink-300 text-primary-500 focus:ring-primary-500">Show email</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_student_id" value="1" {{ (! $p || $p->show_student_id) ? 'checked' : '' }} class="h-4 w-4 rounded border-ink-300 text-primary-500 focus:ring-primary-500">Show student/employee ID</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_phone" value="1" {{ $p?->show_phone ? 'checked' : '' }} class="h-4 w-4 rounded border-ink-300 text-primary-500 focus:ring-primary-500">Show phone</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_friend_list" value="1" {{ (! $p || $p->show_friend_list) ? 'checked' : '' }} class="h-4 w-4 rounded border-ink-300 text-primary-500 focus:ring-primary-500">Show friend list</label>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primary flex-1 py-2.5">Save changes</button>
                    <a href="{{ route('profile.show', $user) }}" class="btn-secondary py-2.5">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
