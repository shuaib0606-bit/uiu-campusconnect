@php $p = $user->profile; @endphp
<x-app-layout title="Edit profile — UIU CampusConnect">
    <div class="mx-auto max-w-xl surface p-6">
        <h1 class="mb-6 text-lg font-semibold">Edit profile</h1>
        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div><label class="mb-1.5 block text-sm font-medium">Photo</label><input type="file" name="avatar" accept="image/*" class="field"></div>
                <div><label class="mb-1.5 block text-sm font-medium">Cover</label><input type="file" name="cover" accept="image/*" class="field"></div>
            </div>

            <div><label class="mb-1.5 block text-sm font-medium">Full name</label><input name="full_name" value="{{ $user->full_name }}" required class="field"></div>

            <div class="grid grid-cols-2 gap-4">
                <div><label class="mb-1.5 block text-sm font-medium">Department</label><input name="department" value="{{ $user->department }}" class="field"></div>
                <div><label class="mb-1.5 block text-sm font-medium">Designation</label><input name="designation" value="{{ $user->designation }}" class="field"></div>
            </div>

            <div><label class="mb-1.5 block text-sm font-medium">Bio</label><textarea name="bio" rows="3" class="field">{{ $p?->bio }}</textarea></div>

            <div class="grid grid-cols-2 gap-4">
                <div><label class="mb-1.5 block text-sm font-medium">Room</label><input name="room" value="{{ $p?->room }}" class="field"></div>
                <div><label class="mb-1.5 block text-sm font-medium">Phone</label><input name="phone" value="{{ $p?->phone }}" class="field"></div>
            </div>

            <div><label class="mb-1.5 block text-sm font-medium">Skills / interests</label><input name="skills" value="{{ $p?->skills }}" class="field"></div>

            <div class="grid grid-cols-3 gap-3 pt-1">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_email" value="1" {{ $p?->show_email ? 'checked' : '' }} class="rounded border-ink-300 text-primary-600">Show email</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_phone" value="1" {{ $p?->show_phone ? 'checked' : '' }} class="rounded border-ink-300 text-primary-600">Show phone</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_friend_list" value="1" {{ $p?->show_friend_list ? 'checked' : '' }} class="rounded border-ink-300 text-primary-600">Show friends</label>
            </div>

            <button type="submit" class="btn-primary w-full py-2.5">Save changes</button>
        </form>
    </div>
</x-app-layout>
