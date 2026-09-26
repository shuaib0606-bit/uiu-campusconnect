<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Links;
use App\Services\Ai\PublicProfile;
use App\Services\Ai\Scopes;

/**
 * The people directory: anyone at the university, as their profile page
 * shows them.
 *
 * Search matches on the fields a person shows. Email and ID are matched
 * only for people who have left those visible - otherwise searching for
 * an address would confirm whose it is, which would leak exactly what the
 * person chose to hide. (The site's own directory matches hidden emails;
 * this is stricter on purpose.)
 */
class PeopleTool extends Tool
{
    public function name(): string
    {
        return 'people';
    }

    public function description(): string
    {
        return 'Find anyone at UIU by name, department or title, and their public profile: department, role, email and ID if they show them, office hours, room, research, clubs. To find people by subject ("who works on networks?"), use find_expertise instead.';
    }

    public function parameters(): array
    {
        return self::object([
            'query' => self::text('A name, department, designation, skill, or a visible email or ID.'),
            'role' => self::choice(['any', 'student', 'faculty', 'official'], 'Optional filter.'),
            'profile' => self::text('A profile link from earlier in this conversation, e.g. "/profile/k3v9x0qz7m". Use it instead of query to look up one person who was already mentioned - a seller, a teacher, a club executive.'),
        ]);
    }

    public function run(array $arguments): array
    {
        $link = $this->arg($arguments, 'profile');

        if ($link !== '') {
            return $this->byLink($link);
        }

        $query = $this->arg($arguments, 'query');
        $role = $this->arg($arguments, 'role');

        if (mb_strlen($query) < 2) {
            return ['error' => 'Give at least two characters of a name or department.'];
        }

        // "Sajid Sir", "Dr. Karim", "Ms Rahman" - honorifics are how
        // students refer to teachers and are not part of anyone's name.
        $clean = trim(preg_replace('/\b(sir|madam|maam|ma\'am|mam|dr|prof|professor|mr|mrs|ms|miss)\b\.?/i', '', $query));
        // % and _ are wildcards in LIKE; a question containing them should
        // search for them, not match everyone.
        $term = '%'.addcslashes($clean !== '' ? $clean : $query, '%_\\').'%';

        $people = Scopes::visiblePeople($this->viewer)
            ->leftJoin('user_profiles as up', 'up.user_id', '=', 'users.id')
            ->select('users.*')
            ->when(in_array($role, ['student', 'faculty', 'official'], true), fn ($q) => $q->where('users.role', $role))
            ->where(fn ($q) => $q
                ->where('users.full_name', 'like', $term)
                ->orWhere('users.department', 'like', $term)
                ->orWhere('users.designation', 'like', $term)
                ->orWhere('up.skills', 'like', $term)
                // Only for people who have not hidden their courses.
                ->orWhere(fn ($w) => $w->where('up.courses_taught', 'like', $term)
                    ->whereIn('up.courses_visibility', \App\Support\Visibility::forMembers()))
                // Only for people who show their email.
                ->orWhere(fn ($w) => $w
                    ->where('users.email', 'like', $term)
                    ->where(fn ($s) => $s->whereNull('up.user_id')->orWhere('up.show_email', true)))
                // Only for people who show their ID.
                ->orWhere(fn ($w) => $w
                    ->where(fn ($i) => $i->where('users.student_id', 'like', $term)->orWhere('users.employee_id', 'like', $term))
                    ->where(fn ($s) => $s->whereNull('up.user_id')->orWhere('up.show_student_id', true))))
            // Faculty and officials first - most questions about a person
            // are about a teacher or an office.
            ->orderByRaw("CASE users.role WHEN 'faculty' THEN 0 WHEN 'official' THEN 1 ELSE 2 END")
            ->orderBy('users.full_name')
            ->limit(8)
            ->get();

        if ($people->isEmpty()) {
            return ['people' => [], 'note' => 'Nobody matching "'.$query.'" who can be shown.'];
        }

        // Full detail when the answer is one or two people; a short card
        // each when it is a list, to keep the reply small.
        $detail = $people->count() <= 2;

        return array_filter([
            'people' => $people->map(fn ($p) => PublicProfile::present($p, $this->viewer, $detail))->values()->all(),
            'note' => $people->count() > 1
                ? 'Several people match. If the conversation already points to one of them, answer about that one only.'
                : null,
        ]);
    }

    /**
     * One person, by the profile link an earlier answer gave.
     *
     * The same gate as a name search - active, not blocked either way - and
     * the same PublicProfile, so a link cannot show more than the profile
     * page would. Asking for an id that is hidden, blocked or unknown gets
     * one answer for all three, so the lookup cannot tell them apart.
     */
    private function byLink(string $link): array
    {
        $key = Links::profileKey($link);

        $person = $key ? Scopes::visiblePeople($this->viewer)->where('users.public_id', $key)->first() : null;

        return $person
            ? ['people' => [PublicProfile::present($person, $this->viewer, true)]]
            : ['people' => [], 'note' => 'That profile is not one that can be shown.'];
    }
}
