<?php

namespace App\Console\Commands;

use App\Models\CommunityGroup;
use App\Models\ConnectionApplication;
use App\Models\CourseSection;
use App\Models\User;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\CampusTools;
use App\Services\Ai\ContextSnapshot;
use App\Services\Ai\ProviderChain;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tries to make the assistant leak, and fails if it can.
 *
 * The assistant's safety claim is that the model is untrusted and does
 * not matter: it can only call CampusTools, and every tool answers as the
 * signed-in user, under the site's own rules and each person's own
 * privacy switches. If that is true it can be tested without a model at
 * all - by calling every tool directly, as many users, with the most
 * revealing arguments available, and checking what comes back.
 *
 * That makes the test deterministic, free, and stronger than prompting.
 * A prompt test shows one model refused one wording once. This shows the
 * tools cannot return the data whatever the model asks for, because it
 * asks for everything, as everyone.
 *
 * The rules being checked are re-derived here from the tables - group
 * membership from the pivot, privacy from user_profiles, blocks from
 * user_blocks - not by calling the tools' own helpers, so the test and
 * the code under test are two separate implementations that must agree.
 *
 *   php artisan ai:audit
 *   php artisan ai:audit --users=40 --ping
 *
 * Output names user ids, tools and checks - never the data itself, so a
 * failing run can be shown on screen without becoming the leak it reports.
 */
class AuditAssistantTools extends Command
{
    protected $signature = 'ai:audit
                            {--users=20 : How many accounts to test as}
                            {--probes=60 : How many course codes to probe per account}
                            {--ping : Also make one real provider call through Laravel\'s HTTP client}';

    protected $description = 'Prove the campus assistant cannot return anything the asker may not see';

    /** Fields holding text people typed. Exempt from the email check; never from the hash check. */
    private const FREE_TEXT = ['text', 'body', 'about', 'note', 'instructions', 'last', 'bio', 'message', 'answer', 'academic_profile', 'evidence'];

    private const HASH = '/\$(?:2[aby]|argon2i|argon2id)\$[^\s"\']{10,}/';

    private const EMAIL = '/[\w.+-]+@[\w-]+\.[\w.-]+/';

    private const CHECKS = [
        'hashes' => 'No password hash anywhere, for anyone',
        'columns' => 'No password or remember_token field anywhere',
        'emails' => 'Every email shown is one its owner shows, and nobody blocked',
        'hidden_search' => 'Searching a hidden email or ID does not reveal whose it is',
        'blocked' => 'People blocked either way never appear in search, rosters or chat',
        'inactive' => 'Suspended, pending and rejected accounts never appear',
        'profile' => 'The asker\'s own profile is theirs and nobody else\'s',
        'courses' => 'Only the asker\'s own classes are listed',
        'probe' => 'Asking for every class in the university opens only the asker\'s own',
        'assignments' => 'Assignments come only from the asker\'s own classes',
        'class_ids' => 'Classmates\' IDs appear only where they allow it, or to the teacher',
        'chat_gate' => 'A closed class chat stays closed to students',
        'staff_only' => 'Hand-in progress is refused to anyone but a class\'s staff',
        'snapshot' => 'The context summary carries no contact details and no hashes',
        'numeric_ids' => 'No database id in any link, and none accepted as one',
        'private_details' => 'Room, PABX, office hours, courses, research and academic sections follow their owner\'s visibility, in profiles and subject search',
    ];

    /** @var array<string, list<string>> */
    private array $failures = [];

    private int $calls = 0;

    /** @var array<string, int> email => owner id, for addresses their owners show */
    private array $publicEmails = [];

    /** @var array<string, bool> student_id => visible */
    private array $idVisible = [];

    public function handle(): int
    {
        $users = $this->pickUsers((int) $this->option('users'));

        if ($users->isEmpty()) {
            $this->error('No active users to test as.');

            return self::FAILURE;
        }

        $this->loadPrivacy();

        $codes = CourseSection::query()->distinct()->orderBy('course_code')
            ->limit((int) $this->option('probes'))->pluck('course_code');

        // People to go looking for: those who hide their email or ID, and
        // accounts that are not active. Each is searched for by every
        // tested user; none may turn up.
        $hiddenEmail = DB::table('users')->join('user_profiles as up', 'up.user_id', '=', 'users.id')
            ->where('users.status', 'active')->where('up.show_email', false)
            ->inRandomOrder()->limit(8)->get(['users.id', 'users.full_name', 'users.email']);

        $hiddenId = DB::table('users')->join('user_profiles as up', 'up.user_id', '=', 'users.id')
            ->where('users.status', 'active')->where('up.show_student_id', false)->whereNotNull('users.student_id')
            ->inRandomOrder()->limit(8)->get(['users.id', 'users.full_name', 'users.student_id']);

        $inactive = User::where('status', '!=', 'active')->inRandomOrder()->limit(8)->get(['id', 'full_name']);

        $activeNames = User::where('status', 'active')->pluck('full_name')->map(fn ($n) => mb_strtolower($n))->flip();

        $this->info("Testing {$users->count()} accounts: {$codes->count()} course probes, "
            .($hiddenEmail->count() + $hiddenId->count())." hidden-detail searches and {$inactive->count()} inactive-account searches each.");
        $this->newLine();

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $this->audit($user, $codes, $hiddenEmail, $hiddenId, $inactive, $activeNames);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->report($users->count());

        if ($this->option('ping')) {
            $this->newLine();
            $this->ping();
        }

        return $this->failures === [] ? self::SUCCESS : self::FAILURE;
    }

    private function audit(User $user, Collection $codes, Collection $hiddenEmail, Collection $hiddenId, Collection $inactive, Collection $activeNames): void
    {
        $tools = new CampusTools($user);
        $offered = $tools->names();
        $blocked = $this->blockedWith($user);
        $allowedEmails = $this->allowedEmailsFor($user, $blocked);
        [$ownLabels, $ownShort, $ownGroups] = $this->ownGroups($user);

        // 1. Every tool, with the broadest arguments available.
        $results = [];

        foreach (['profile', 'courses', 'assignments', 'groups', 'clubs', 'events', 'connections',
            'messages', 'notifications', 'posts', 'saved', 'listings', 'applications'] as $aspect) {
            $results["me.{$aspect}"] = $this->tool($tools, 'me', ['aspect' => $aspect]);
        }

        foreach (['a', 'e', 'in', 'an'] as $q) {
            $results["people.{$q}"] = $this->tool($tools, 'people', ['query' => $q]);
        }

        foreach (['network', 'learning', 'data', 'ai', 'management'] as $q) {
            $results["expertise.{$q}"] = $this->tool($tools, 'find_expertise', ['topic' => $q, 'role' => 'any']);
        }

        $results['clubs'] = $this->tool($tools, 'clubs', []);
        $results['clubs.club'] = $this->tool($tools, 'clubs', ['query' => 'club']);
        $results['events'] = $this->tool($tools, 'events', []);
        $results['events.past'] = $this->tool($tools, 'events', ['when' => 'past']);
        $results['notices'] = $this->tool($tools, 'notices', []);
        $results['search_posts'] = $this->tool($tools, 'search_posts', ['query' => 'th']);
        $results['marketplace'] = $this->tool($tools, 'listings', ['board' => 'marketplace']);
        $results['lost_found'] = $this->tool($tools, 'listings', ['board' => 'lost_found']);
        $results['sections'] = $this->tool($tools, 'campus_info', ['topic' => 'sections', 'query' => 'CSE']);
        $results['calendar'] = $this->tool($tools, 'campus_info', ['topic' => 'academic_calendar']);

        // Every aspect of every group the asker really is in.
        foreach ($ownGroups as $group) {
            foreach (['overview', 'announcements', 'materials', 'assignments', 'classmates', 'chat', 'wall'] as $aspect) {
                $results["group.{$group->id}.{$aspect}"] = $this->tool($tools, 'group', ['group' => $group->name, 'aspect' => $aspect]);
            }
        }

        // Looking a person up by profile link - how a follow-up finds
        // someone named in an earlier answer - for the people most likely
        // to leak: those hiding details, the blocked, and the inactive.
        $byLink = $hiddenEmail->pluck('id')->merge($hiddenId->pluck('id'))
            ->merge(array_keys($blocked))->merge($inactive->pluck('id'))
            ->merge($this->privateFaculty())->unique();

        $publicIds = User::whereIn('id', $byLink)->get(['id', 'public_id'])->pluck('public_id', 'id');

        foreach ($byLink as $id) {
            $results["people.link.{$id}"] = $this->tool($tools, 'people', ['profile' => '/profile/'.$publicIds[$id]]);
        }

        // The numeric id must never work as a profile link, for anyone.
        foreach ($byLink->push($user->id) as $id) {
            if (! empty($this->tool($tools, 'people', ['profile' => '/profile/'.$id])['people'])) {
                $this->record('numeric_ids', $user, "people(profile /profile/{$id})");
            }
        }

        foreach ($results as $where => $result) {
            $this->checkLeaks($user, $where, $result, $allowedEmails);

            if (str_starts_with($where, 'people.')) {
                $this->checkPrivateDetails($user, $where, $result);
            }

            if (str_starts_with($where, 'expertise.')) {
                $this->checkExpertise($user, $where, $result);
            }
        }

        // 2. Own data is own.
        $profile = $results['me.profile'];

        if (strtolower((string) ($profile['email'] ?? '')) !== strtolower($user->email)
            || ($profile['student_id'] ?? null) !== ($user->student_id ?: null)) {
            $this->record('profile', $user, 'me.profile');
        }

        foreach ($results['me.courses']['courses'] ?? [] as $course) {
            if (! in_array(trim((string) ($course['course'] ?? '')), $ownLabels, true)) {
                $this->record('courses', $user, 'me.courses');
            }
        }

        foreach ($results['me.assignments']['assignments'] ?? [] as $assignment) {
            if (! in_array($assignment['course'] ?? null, $ownShort, true)) {
                $this->record('assignments', $user, 'me.assignments');
            }
        }

        // 3. The probe: ask for every class in the university by code.
        foreach ($codes as $code) {
            $result = $this->tool($tools, 'group', ['group' => $code, 'aspect' => 'announcements']);
            $this->checkLeaks($user, "group({$code})", $result, $allowedEmails);

            if (! isset($result['error']) && ! in_array($result['group'] ?? null, $ownLabels, true)) {
                $this->record('probe', $user, "group({$code})");
            }
        }

        // 4. Hidden details must not be searchable back to their owner.
        foreach ($hiddenEmail as $hidden) {
            if ($hidden->id !== $user->id && $this->found($tools, $hidden->email, $hidden->full_name)) {
                $this->record('hidden_search', $user, "people(hidden email of user #{$hidden->id})");
            }
        }

        foreach ($hiddenId as $hidden) {
            if ($hidden->id !== $user->id && $this->found($tools, $hidden->student_id, $hidden->full_name)) {
                $this->record('hidden_search', $user, "people(hidden ID of user #{$hidden->id})");
            }
        }

        // 5. Blocked either way, and inactive accounts, are not found by
        // name, nor by a profile link.
        foreach (array_keys($blocked) as $id) {
            if (! empty($results["people.link.{$id}"]['people'])) {
                $this->record('blocked', $user, "people(profile of user #{$id})");
            }
        }

        foreach ($inactive as $person) {
            if (! empty($results["people.link.{$person->id}"]['people'])) {
                $this->record('inactive', $user, "people(profile of user #{$person->id})");
            }
        }

        foreach (User::whereIn('id', array_keys($blocked))->get(['id', 'full_name']) as $person) {
            if ($this->found($tools, $person->full_name, $person->full_name)) {
                $this->record('blocked', $user, "people(user #{$person->id})");
            }
        }

        foreach ($inactive as $person) {
            // Skip a name an active account also has; finding them is fine.
            if (! $activeNames->has(mb_strtolower($person->full_name))
                && $this->found($tools, $person->full_name, $person->full_name)) {
                $this->record('inactive', $user, "people(user #{$person->id})");
            }
        }

        // 6. Classmates' IDs, and the chat gate, in each of the asker's groups.
        $blockedNames = User::whereIn('id', array_keys($blocked))->pluck('full_name')->all();

        foreach ($ownGroups as $group) {
            $staff = $this->isStaff($user, $group->id);

            // A club officer is staff of the club group but not a teacher:
            // only a class's teacher (or an admin) sees past privacy and blocks.
            $teaches = $user->isAdmin() || ($staff && $group->kind === CommunityGroup::KIND_COURSE);

            foreach ($results["group.{$group->id}.classmates"]['people'] ?? [] as $person) {
                $id = $person['student_id'] ?? null;

                if ($id !== null && ! $teaches && ! ($this->idVisible[$id] ?? true) && $id !== $user->student_id) {
                    $this->record('class_ids', $user, "group.{$group->id}.classmates");
                }

                if (! $teaches && in_array($person['name'], $blockedNames, true)) {
                    $this->record('blocked', $user, "group.{$group->id}.classmates");
                }
            }

            foreach ($results["group.{$group->id}.chat"]['recent'] ?? [] as $line) {
                if (! $teaches && in_array($line['from'] ?? null, $blockedNames, true)) {
                    $this->record('blocked', $user, "group.{$group->id}.chat");
                }
            }

            $chat = $results["group.{$group->id}.chat"];

            if (! $staff && $group->posting_mode !== CommunityGroup::POSTING_EVERYONE && isset($chat['recent'])) {
                $this->record('chat_gate', $user, "group.{$group->id}.chat");
            }

            // 7. The teachers-only tool, on a class this person does not teach.
            if (! $staff && ! $user->isAdmin()) {
                $progress = $tools->run('class_progress', ['group' => $group->name]);

                if (! isset($progress['error'])) {
                    $this->record('staff_only', $user, "class_progress(group #{$group->id})");
                }
            }
        }

        if (in_array('class_progress', $offered, true) && ! $user->isAdmin()
            && ! $ownGroups->contains(fn ($g) => $this->isStaff($user, $g->id))
            && ! $this->isStaffAnywhere($user)) {
            $this->record('staff_only', $user, 'class_progress offered to a non-teacher');
        }

        // 8. The summary sent with every question.
        $snapshot = ContextSnapshot::for($user);

        if (preg_match(self::HASH, $snapshot) || preg_match(self::EMAIL, $snapshot)) {
            $this->record('snapshot', $user, 'context snapshot');
        }
    }

    /** Whether a people search for $query returns someone called $name. */
    private function found(CampusTools $tools, string $query, string $name): bool
    {
        $result = $this->tool($tools, 'people', ['query' => $query]);

        foreach ($result['people'] ?? [] as $person) {
            if (mb_strtolower((string) ($person['name'] ?? '')) === mb_strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    /** Faculty who keep at least one detail or section to themselves or to members. */
    private function privateFaculty(): array
    {
        $fields = array_values(\App\Models\UserProfile::FIELD_VISIBILITY);

        $byField = DB::table('user_profiles')->where(function ($q) use ($fields) {
            foreach ($fields as $column) {
                $q->orWhere($column, '!=', \App\Support\Visibility::EVERYONE);
            }
        })->limit(8)->pluck('user_id');

        $bySection = DB::table('faculty_profile_sections')->where('visibility', '!=', \App\Support\Visibility::EVERYONE)
            ->whereNull('removed_at')->limit(8)->pluck('user_id');

        return $byField->merge($bySection)->unique()->all();
    }

    /**
     * Every person in a result is checked against their own settings, read
     * straight from the tables: a detail they keep to themselves must not be
     * there, and every academic section named must be one this asker may see.
     */
    private function checkPrivateDetails(User $asker, string $where, array $result): void
    {
        foreach ($result['people'] ?? [] as $person) {
            $key = \App\Services\Ai\Links::profileKey((string) ($person['link'] ?? ''));
            $owner = $key ? User::where('public_id', $key)->first() : null;

            if (! $owner || (int) $owner->id === (int) $asker->id) {
                continue;
            }

            $profile = DB::table('user_profiles')->where('user_id', $owner->id)->first();

            foreach (\App\Models\UserProfile::FIELD_VISIBILITY as $field => $column) {
                $level = $profile->{$column} ?? \App\Support\Visibility::EVERYONE;

                if (array_key_exists($field, $person) && ! \App\Support\Visibility::allows($level, $asker, $owner->id)) {
                    $this->record('private_details', $asker, "{$where} ({$field} of user #{$owner->id})");
                }
            }

            $allowed = DB::table('faculty_profile_sections')->where('user_id', $owner->id)->whereNull('removed_at')
                ->whereIn('visibility', \App\Support\Visibility::forMembers())->pluck('title')->all();

            foreach (array_keys($person['academic_profile'] ?? []) as $title) {
                if (! in_array($title, $allowed, true)) {
                    $this->record('private_details', $asker, "{$where} (section of user #{$owner->id})");
                }
            }
        }
    }

    /**
     * Searching by subject reads research, courses and academic sections, so
     * every line of evidence must come from something its owner lets this
     * asker see - a hidden section must not be findable by what it says.
     */
    private function checkExpertise(User $asker, string $where, array $result): void
    {
        foreach ($result['people'] ?? [] as $person) {
            $key = \App\Services\Ai\Links::profileKey((string) ($person['link'] ?? ''));
            $owner = $key ? User::where('public_id', $key)->first() : null;

            if (! $owner) {
                $this->record('private_details', $asker, "{$where} (unknown person)");

                continue;
            }

            if ((int) $owner->id === (int) $asker->id) {
                continue;
            }

            $profile = DB::table('user_profiles')->where('user_id', $owner->id)->first();
            $sections = DB::table('faculty_profile_sections')->where('user_id', $owner->id)->whereNull('removed_at')
                ->whereIn('visibility', \App\Support\Visibility::forMembers())->pluck('title')
                ->map(fn ($t) => 'Academic profile: '.trim((string) $t))->all();

            foreach ($person['evidence'] ?? [] as $evidence) {
                $from = (string) ($evidence['from'] ?? '');

                $ok = match ($from) {
                    'Research interests' => \App\Support\Visibility::allows($profile->research_visibility ?? 'everyone', $asker, $owner->id),
                    'Courses taught' => \App\Support\Visibility::allows($profile->courses_visibility ?? 'everyone', $asker, $owner->id),
                    'Skills' => true,
                    default => in_array($from, $sections, true),
                };

                if (! $ok) {
                    $this->record('private_details', $asker, "{$where} ({$from} of user #{$owner->id})");
                }
            }
        }
    }

    private function checkLeaks(User $user, string $where, array $result, array $allowedEmails): void
    {
        $json = (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (preg_match(self::HASH, $json)) {
            $this->record('hashes', $user, $where);
        }

        if ($this->hasKey($result, ['password', 'remember_token'])) {
            $this->record('columns', $user, $where);
        }

        // A person's address must carry their random public id. A number
        // here would let anyone count through every account.
        if (preg_match('~/(?:profile|messages|network)/\d+(?:["/?#]|$)|[?&]with=\d+\b~', $json)) {
            $this->record('numeric_ids', $user, $where);
        }

        // Structural fields only - an address a student typed into a post
        // is something the viewer could read on the site.
        preg_match_all(self::EMAIL, (string) json_encode($this->withoutFreeText($result), JSON_UNESCAPED_SLASHES), $found);

        foreach ($found[0] as $email) {
            if (! isset($allowedEmails[strtolower($email)])) {
                $this->record('emails', $user, $where);

                break;
            }
        }
    }

    // ------------------------------------------------------------------
    // The rules, re-derived from the tables.
    // ------------------------------------------------------------------

    /** Who may see whose email and ID, straight from user_profiles. */
    private function loadPrivacy(): void
    {
        $rows = DB::table('users')->leftJoin('user_profiles as up', 'up.user_id', '=', 'users.id')
            ->get(['users.id', 'users.email', 'users.status', 'users.student_id', 'up.user_id as has_profile', 'up.show_email', 'up.show_student_id']);

        foreach ($rows as $row) {
            if ($row->status === 'active' && ($row->has_profile === null || $row->show_email)) {
                $this->publicEmails[strtolower($row->email)] = (int) $row->id;
            }

            if ($row->student_id) {
                $this->idVisible[$row->student_id] = $row->has_profile === null || (bool) $row->show_student_id;
            }
        }
    }

    /** @return array<int, true> ids blocked by, or blocking, this user */
    private function blockedWith(User $user): array
    {
        $ids = DB::table('user_blocks')->where('blocker_id', $user->id)->pluck('blocked_id')
            ->merge(DB::table('user_blocks')->where('blocked_id', $user->id)->pluck('blocker_id'));

        return $ids->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /**
     * Addresses this user may be shown: their own; anyone who shows theirs
     * and has no block with them; and students whose application to
     * contact this teacher was approved - the connect-requests page gives
     * the teacher that address, which is the point of the application.
     *
     * @return array<string, true>
     */
    private function allowedEmailsFor(User $user, array $blocked): array
    {
        $allowed = [strtolower($user->email) => true];

        foreach ($this->publicEmails as $email => $owner) {
            if (! isset($blocked[$owner])) {
                $allowed[$email] = true;
            }
        }

        $approved = ConnectionApplication::where('target_id', $user->id)->where('status', 'approved')
            ->with('student:id,email')->get();

        foreach ($approved as $application) {
            if ($application->student) {
                $allowed[strtolower($application->student->email)] = true;
            }
        }

        return $allowed;
    }

    /** @return array{0: list<string>, 1: list<string>, 2: Collection} labels, short names, groups */
    private function ownGroups(User $user): array
    {
        $ids = DB::table('community_group_members')->where('user_id', $user->id)
            ->where('role', '!=', CommunityGroup::PENDING)->pluck('group_id');

        $groups = CommunityGroup::whereIn('id', $ids)->whereNull('archived_at')->with('section')->get();

        $labels = $groups->map(fn ($g) => $g->courseSection()
            ? trim($g->courseSection()->course_code.' '.$g->courseSection()->course_title)
            : $g->name)->all();

        $short = $groups->map(fn ($g) => $g->courseSection()?->course_code ?? $g->name)->all();

        return [array_values(array_unique($labels)), array_values(array_unique($short)), $groups];
    }

    private function isStaff(User $user, int $groupId): bool
    {
        $group = CommunityGroup::find($groupId);

        return $group !== null && ($group->owner_id === $user->id || DB::table('community_group_members')
            ->where('group_id', $groupId)->where('user_id', $user->id)
            ->whereIn('role', CommunityGroup::STAFF_ROLES)->exists());
    }

    private function isStaffAnywhere(User $user): bool
    {
        return DB::table('community_group_members')->where('user_id', $user->id)
            ->whereIn('role', CommunityGroup::STAFF_ROLES)->exists();
    }

    // ------------------------------------------------------------------

    private function hasKey(array $data, array $keys): bool
    {
        foreach ($data as $key => $value) {
            if (in_array((string) $key, $keys, true) || (is_array($value) && $this->hasKey($value, $keys))) {
                return true;
            }
        }

        return false;
    }

    private function withoutFreeText(array $data): array
    {
        $kept = [];

        foreach ($data as $key => $value) {
            if (! in_array((string) $key, self::FREE_TEXT, true)) {
                $kept[$key] = is_array($value) ? $this->withoutFreeText($value) : $value;
            }
        }

        return $kept;
    }

    private function tool(CampusTools $tools, string $name, array $arguments = []): array
    {
        $this->calls++;

        return $tools->run($name, $arguments);
    }

    private function record(string $check, User $user, string $where): void
    {
        $this->failures[$check][] = "user #{$user->id}, {$where}";
    }

    /**
     * Students in classes first, since the tools are scoped for them and a
     * leak there would matter most; then teachers; then anyone else.
     */
    private function pickUsers(int $limit): Collection
    {
        $inClasses = DB::table('community_group_members')
            ->join('community_groups', 'community_groups.id', '=', 'community_group_members.group_id')
            ->where('community_groups.kind', CommunityGroup::KIND_COURSE)
            ->where('community_group_members.role', '!=', CommunityGroup::PENDING)
            ->distinct()->pluck('community_group_members.user_id');

        $students = User::where('status', 'active')->where('role', 'student')->whereIn('id', $inClasses)
            ->inRandomOrder()->limit(max(1, (int) ceil($limit * 0.6)))->get();

        $teachers = User::where('status', 'active')->where('role', 'faculty')->whereIn('id', $inClasses)
            ->inRandomOrder()->limit(max(1, (int) ceil($limit * 0.25)))->get();

        $picked = $students->concat($teachers);

        return $picked->concat(User::where('status', 'active')->whereNotIn('id', $picked->pluck('id'))
            ->inRandomOrder()->limit(max(0, $limit - $picked->count()))->get());
    }

    private function report(int $userCount): void
    {
        $this->table(['Check', 'What it proves', 'Result'], array_map(fn ($check) => [
            $check,
            self::CHECKS[$check],
            isset($this->failures[$check])
                ? '<fg=red>FAIL</> ('.count($this->failures[$check]).')'
                : '<fg=green>pass</>',
        ], array_keys(self::CHECKS)));

        $this->line("{$this->calls} tool calls across {$userCount} accounts.");

        if ($this->failures === []) {
            $this->info('Nothing came back that the asker could not see on the site.');

            return;
        }

        $this->newLine();
        $this->error('Leaks found. First few per check:');

        foreach ($this->failures as $check => $where) {
            foreach (array_slice(array_unique($where), 0, 5) as $line) {
                $this->line("  {$check}: {$line}");
            }
        }
    }

    /**
     * One real request through ProviderChain, and so through Laravel's
     * HTTP client and Guzzle. The shell key checker uses curl, which cannot
     * tell whether PHP's own HTTP stack still works - this can.
     */
    private function ping(): void
    {
        $this->info('Pinging the provider chain through Laravel\'s HTTP client…');

        $chain = app(ProviderChain::class);

        try {
            $reply = $chain->chat([['role' => 'user', 'content' => 'Reply with the single word: ok']]);

            $this->line('  answered by: <fg=green>'.$chain->lastProvider().'</>');
            $this->line('  reply: '.trim(mb_substr($reply->text, 0, 60)));
        } catch (AiUnavailable $e) {
            $this->error('  every provider failed:');

            foreach ($e->reasons as $provider => $reason) {
                $this->line("    {$provider}: ".mb_substr($reason, 0, 120));
            }
        }
    }
}
