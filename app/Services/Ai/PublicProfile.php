<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * What the assistant may say about another person.
 *
 * The profile page is the model: anything it shows every signed-in user,
 * the assistant may repeat. The three fields people control - email,
 * student or employee ID, and phone - follow that person's own switches
 * in their profile settings, exactly as the profile page reads them.
 *
 * Two places elsewhere on the site show more than this - the class list
 * prints student IDs regardless of show_student_id, and a user's @handle
 * is built from their student ID. The assistant follows the person's
 * setting instead of those pages. It never returns a handle for the same
 * reason.
 *
 * Never included, for anyone: password, remember token, year of study
 * (the profile page does not show it), signup review notes, the ID card
 * upload, last login, account status, authority level.
 */
class PublicProfile
{
    /** @return array<string, mixed> */
    public static function present(User $person, User $viewer, bool $detail = false): array
    {
        $self = (int) $person->id === (int) $viewer->id;
        $profile = $person->profile;

        $out = [
            'name' => $person->full_name,
            'role' => $person->roleLabel(),
            'title' => $person->designation ?: null,
            'department' => $person->department ?: null,
            'link' => Links::profile($person),
        ];

        // Mirrors profile/show.blade.php line for line: a person with no
        // profile row has never turned these off, so the defaults apply.
        if ($self || ! $profile || $profile->show_email) {
            $out['email'] = $person->email;
        } else {
            $out['email'] = 'not shown - this person keeps it private';
        }

        if ($self || ! $profile || $profile->show_student_id) {
            $id = $person->student_id ?: $person->employee_id;

            if ($id) {
                $out[$person->student_id ? 'student_id' : 'employee_id'] = $id;
            }
        }

        if (($self || ($profile && $profile->show_phone)) && $profile?->phone) {
            $out['phone'] = $profile->phone;
        }

        if (! $detail) {
            return $out;
        }

        $out += array_filter([
            // Each of these follows the owner's own "who can see this" choice.
            'room' => $profile?->visible('room', $viewer),
            'pabx' => $profile?->visible('pabx', $viewer),
            'office_hours' => $profile?->visible('office_hours', $viewer),
            'courses_taught' => $profile?->visible('courses_taught', $viewer),
            'research_interests' => $profile?->visible('research_interests', $viewer),
            'bio' => $profile?->bio ? Str::limit(strip_tags($profile->bio), 240) : null,
            'skills' => $profile?->skills,
            'joined' => $person->created_at?->format('F Y'),
        ]);

        $clubs = $person->clubs()->where('clubs.status', 'active')->get()
            ->map(fn ($club) => $club->name.self::role($club->pivot->club_role ?? null))
            ->values()
            ->all();

        if ($clubs !== []) {
            $out['clubs'] = $clubs;
        }

        // The academic profile: which sections exist, with a short excerpt of
        // each, so "where did she do her PhD" can be answered without sending
        // a whole publication list. Only sections this asker may see.
        $sections = $person->facultySections()->visibleTo($viewer, $person->id)->get();

        if ($sections->isNotEmpty()) {
            $out['academic_profile'] = $sections->mapWithKeys(fn ($s) => [$s->title => $s->plain(220)])->all();
        }

        return $out;
    }

    /** " (President)", or nothing for an ordinary member. */
    private static function role(?string $role): string
    {
        $role = trim((string) $role);

        return $role === '' || in_array(Str::lower($role), ['member', 'general member'], true)
            ? ''
            : ' ('.$role.')';
    }
}
