<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\ClubPanelChange;
use App\Models\ClubRoleRequest;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\CommunityGroup;
use App\Models\CommunityGroupFile;
use App\Models\CourseEnrollment;
use App\Models\CourseSection;
use App\Models\Event;
use App\Models\LostFoundClaim;
use App\Models\LostFoundItem;
use App\Models\MarketplaceItem;
use App\Models\Notice;
use App\Models\PasswordResetRequest;
use App\Models\Post;
use App\Models\PostLink;
use App\Models\PostMedia;
use App\Models\ProfileReport;
use App\Models\Report;
use App\Models\Trimester;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\GroupSync;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fills an empty site with something to click on. Everything it makes is owned
 * by accounts on one throwaway domain, so --wipe can take it all back out
 * without touching the real faculty records.
 */
class SeedDemoContent extends Command
{
    protected $signature = 'uiu:demo-content
                            {--wipe : Remove everything this command created}
                            {--password=demo1234 : Password for the sample accounts}';

    protected $description = 'Create sample students, clubs, events and listings for testing';

    /** Every sample account lives here, which is what makes the wipe exact. */
    private const DOMAIN = 'demo.uiu.test';

    /**
     * The one sample row that deliberately matches no account. It has no
     * user_id, so the wipe cannot find it by owner and matches this instead.
     */
    private const ORPHAN_IDENTIFIER = 'someone.who.left@example.com';

    /** Demo trimester codes, so the wipe can find them without guessing. */
    private const TRIMESTER_CURRENT = 'DEMO-2026-3';

    private const TRIMESTER_PAST = 'DEMO-2026-2';

    public function handle(): int
    {
        return $this->option('wipe') ? $this->wipe() : $this->fill();
    }

    private function fill(): int
    {
        if (User::where('email', 'like', '%@'.self::DOMAIN)->exists()) {
            $this->error('Sample data is already here. Run with --wipe first.');

            return self::FAILURE;
        }

        $password = (string) $this->option('password');

        DB::transaction(function () use ($password) {
            $people = $this->people($password);
            $clubs = $this->clubs($people);

            $this->groups($people);
            $this->events($people, $clubs);
            $this->marketplace($people);
            $this->lostFound($people);
            $this->notices($people);

            $posts = $this->posts($people, $clubs);
            $this->moderation($people, $posts);
            $this->recoveries($people);
            $this->signups($password);
            $this->academics($people);
        });

        $this->newLine();
        $this->info('Sample data created.');
        $this->table(['Account', 'Role', 'Password'], [
            ['tanvir@'.self::DOMAIN, 'student', $password],
            ['mehjabin@'.self::DOMAIN, 'student', $password],
            ['registrar.office@'.self::DOMAIN, 'official', $password],
        ]);
        $this->warn('These accounts share one password. Run uiu:demo-content --wipe before anyone else uses the site.');

        return self::SUCCESS;
    }

    private function wipe(): int
    {
        $ids = User::where('email', 'like', '%@'.self::DOMAIN)->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Nothing to remove.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids) {
            // A sample club is one a sample account moderates.
            $clubIds = Club::whereIn('moderator_id', $ids)->pluck('id');
            $eventIds = Event::whereIn('organizer_user_id', $ids)->orWhereIn('club_id', $clubIds)->pluck('id');

            // Nothing here uploads a picture, so only the rows have to go.
            $this->forgetMedia(Event::class, $eventIds);
            $this->forgetMedia(Club::class, $clubIds);
            $this->forgetMedia(MarketplaceItem::class, MarketplaceItem::whereIn('user_id', $ids)->pluck('id'));
            $this->forgetMedia(LostFoundItem::class, LostFoundItem::whereIn('user_id', $ids)->pluck('id'));
            $this->forgetMedia(Notice::class, Notice::whereIn('issuer_id', $ids)->pluck('id'));

            // Reports first: they point at the posts and comments below, and a
            // report left behind would sit in the moderation queue for ever.
            $postIds = Post::whereIn('user_id', $ids)->pluck('id');
            $commentIds = Comment::whereIn('user_id', $ids)->orWhereIn('post_id', $postIds)->pluck('id');

            Report::whereIn('reporter_id', $ids)->orWhereIn('post_id', $postIds)->delete();
            CommentReport::whereIn('reporter_id', $ids)->orWhereIn('comment_id', $commentIds)->delete();
            ProfileReport::whereIn('reporter_id', $ids)->orWhereIn('reported_user_id', $ids)->delete();
            PasswordResetRequest::whereIn('user_id', $ids)
                ->orWhere('submitted_identifier', self::ORPHAN_IDENTIFIER)
                ->delete();

            Comment::whereIn('id', $commentIds)->delete();

            // Deleting the trimester cascades to its sections and enrolments;
            // the class groups themselves are removed here because they point
            // at sections by a plain column, not a foreign key.
            $demoSections = CourseSection::whereIn(
                'trimester_id',
                Trimester::whereIn('code', [self::TRIMESTER_CURRENT, self::TRIMESTER_PAST])->pluck('id')
            )->pluck('id');

            if ($demoSections->isNotEmpty()) {
                CommunityGroup::where('kind', CommunityGroup::KIND_COURSE)
                    ->whereIn('source_id', $demoSections)
                    ->delete();
            }

            Trimester::whereIn('code', [self::TRIMESTER_CURRENT, self::TRIMESTER_PAST])->delete();

            CommunityGroup::whereIn('owner_id', $ids)->delete();
            Event::whereIn('id', $eventIds)->delete();
            Club::whereIn('id', $clubIds)->delete();
            MarketplaceItem::whereIn('user_id', $ids)->delete();
            LostFoundItem::whereIn('user_id', $ids)->delete();
            Notice::whereIn('issuer_id', $ids)->delete();
            ClubRoleRequest::whereIn('nominated_by', $ids)->orWhereIn('nominee_id', $ids)->delete();
            ClubPanelChange::whereIn('club_id', $clubIds)->orWhereIn('proposed_by', $ids)->delete();
            // The one real file the seeder writes. post_media rows cascade
            // with the post; the file on disk does not.
            Storage::disk(PostMedia::PRIVATE_DISK)->delete('posts/demo-cse2213-week4-notes.txt');

            Post::whereIn('user_id', $ids)->delete();

            User::whereIn('id', $ids)->delete();
        });

        $this->info('Removed '.$ids->count().' sample accounts and everything they posted.');

        return self::SUCCESS;
    }

    private function forgetMedia(string $type, $ids): void
    {
        if ($ids->isNotEmpty()) {
            DB::table('media')->where('mediable_type', $type)->whereIn('mediable_id', $ids)->delete();
        }
    }

    /** created_at is not fillable, so spread-out dates are written afterwards. */
    private function backdate(Model $model, CarbonInterface $when): void
    {
        $model->timestamps = false;
        $model->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
        $model->timestamps = true;
    }

    /** @return array<string, User> */
    private function people(string $password): array
    {
        $rows = [
            ['tanvir', 'Tanvir Hasan', 'student', 'Computer Science and Engineering', '011201234', 'Third year', 'Laravel, MySQL, Football'],
            ['mehjabin', 'Mehjabin Rahman', 'student', 'Computer Science and Engineering', '011201288', 'Final year', 'Machine learning, Debating'],
            ['rafi', 'Rafiul Islam', 'student', 'Electrical and Electronic Engineering', '011301102', 'Second year', 'Robotics, Arduino'],
            ['nusrat', 'Nusrat Jahan', 'student', 'Business Administration', '011401340', 'Third year', 'Marketing, Photography'],
            ['sabbir', 'Sabbir Ahmed', 'student', 'Computer Science and Engineering', '011201455', 'First year', 'Competitive programming'],
            ['anika', 'Anika Tabassum', 'student', 'Economics', '011501077', 'Final year', 'Research, Public speaking'],
            ['jubayer', 'Jubayer Alam', 'student', 'Civil Engineering', '011601211', 'Second year', 'AutoCAD, Cricket'],
            ['prithula', 'Prithula Saha', 'student', 'Pharmacy', '011701019', 'Third year', 'Volunteering, Chemistry'],
        ];

        $people = [];

        foreach ($rows as [$handle, $name, $role, $department, $studentId, $year, $skills]) {
            $people[$handle] = $this->account($handle, $name, $role, $password, [
                'department' => $department,
                'student_id' => $studentId,
                'year' => $year,
            ], $skills);
        }

        foreach ([
            ['shahriar', 'Dr Shahriar Karim', 'Computer Science and Engineering', 'Associate Professor', 'EMP-DEMO-11'],
            ['farzana', 'Farzana Yeasmin', 'Electrical and Electronic Engineering', 'Senior Lecturer', 'EMP-DEMO-12'],
        ] as [$handle, $name, $department, $designation, $employeeId]) {
            $people[$handle] = $this->account($handle, $name, 'faculty', $password, [
                'department' => $department,
                'designation' => $designation,
                'employee_id' => $employeeId,
                'authority_level' => 'faculty',
            ], 'Teaching, Research');
        }

        $people['office'] = $this->account('registrar.office', 'Registrar Office Desk', 'official', $password, [
            'department' => 'Office of the Registrar',
            'designation' => 'Registrar Desk',
            'employee_id' => 'REG-DEMO',
            // canIssueNotices() checks this, so without it the publish form never shows.
            'authority_level' => 'registrar',
        ], null);

        return $people;
    }

    private function account(string $handle, string $name, string $role, string $password, array $extra, ?string $skills): User
    {
        $user = User::create([
            'full_name' => $name,
            'email' => $handle.'@'.self::DOMAIN,
            'password' => Hash::make($password),
            'role' => $role,
            'status' => 'active',
            'is_verified' => true,
            'avatar_initials' => $this->initials($name),
            ...$extra,
        ]);

        UserProfile::create([
            'user_id' => $user->id,
            'bio' => $role === 'student'
                ? 'Studying at United International University.'
                : 'Handles student records and office notices.',
            'skills' => $skills,
        ]);

        return $user;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return Str::upper(Str::substr($parts[0] ?? 'U', 0, 1).Str::substr($parts[1] ?? '', 0, 1));
    }

    /** @return array<string, Club> */
    private function clubs(array $people): array
    {
        $rows = [
            ['UIU Computer Club', 'Technology', 'Workshops, contests and project nights for anyone who writes code.', 'Sunday 4:00 PM', 'Room 421'],
            ['UIU Robotics Club', 'Technology', 'Line followers, drones and the annual robo soccer team.', 'Tuesday 3:30 PM', 'Robotics Lab'],
            ['UIU Debating Society', 'Culture', 'Parliamentary debate practice in Bangla and English.', 'Wednesday 5:00 PM', 'Auditorium Annex'],
            ['UIU Photography Club', 'Culture', 'Photo walks around Dhaka and monthly print reviews.', 'Thursday 4:00 PM', 'Room 312'],
            ['UIU Sports Club', 'Sports', 'Inter-department cricket, football and badminton.', 'Friday 9:00 AM', 'Campus Ground'],
            ['UIU Career Forum', 'Career', 'CV clinics, mock interviews and employer sessions.', 'Monday 2:00 PM', 'Career Services'],
        ];

        // A club moderator is a member of staff, appointed by a super admin.
        // The students who run the club day to day hold panel designations
        // instead, and those change hands every panel handover.
        $staff = [$people['shahriar'], $people['farzana'], $people['office']];
        $panelRoles = ['President', 'General Secretary', 'Treasurer'];
        $clubs = [];

        foreach ($rows as $i => [$name, $category, $description, $time, $place]) {
            $club = Club::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'category' => $category,
                'description' => $description,
                'meeting_time' => $time,
                'meeting_place' => $place,
                'moderator_id' => $staff[$i % count($staff)]->id,
                'status' => 'active',
            ]);

            // The moderator is a member of their own club.
            DB::table('club_members')->insertOrIgnore([
                'club_id' => $club->id,
                'user_id' => $club->moderator_id,
                'club_role' => 'Moderator',
                'can_post_events' => true,
                'joined_at' => now()->subDays(320),
            ]);

            // Students: the first three hold the panel designations, the rest
            // are ordinary members. A club with no members reads as broken.
            $students = collect($people)
                ->filter(fn (User $person) => $person->role === 'student')
                ->shuffle()
                ->take(6)
                ->values();

            foreach ($students as $j => $member) {
                $role = $panelRoles[$j] ?? 'member';

                DB::table('club_members')->insertOrIgnore([
                    'club_id' => $club->id,
                    'user_id' => $member->id,
                    'club_role' => $role,
                    'can_post_events' => $role !== 'member',
                    'joined_at' => now()->subDays(random_int(10, 300)),
                ]);
            }

            $clubs[$club->slug] = $club;
        }

        return $clubs;
    }

    private function groups(array $people): void
    {
        // The fifth value is the visibility. One private group is seeded on
        // purpose, because "members only" is the case worth being able to see.
        $rows = [
            ['CSE 2213 Data Structures', 'course', 'Computer Science and Engineering', 'Slides, past questions and help before the quizzes.', 'public'],
            ['BSCSE Batch 231', 'batch', 'Computer Science and Engineering', 'Anything that affects our batch: routines, registration, results.', 'public'],
            ['Final Year Project Group A', 'project', 'Computer Science and Engineering', 'Our FYP workspace. Meeting notes and draft chapters go here.', 'private'],
            ['Programming Help Desk', 'community', null, 'Stuck on an assignment? Post the error and someone will look.', 'public'],
            ['EEE Lab Reports', 'course', 'Electrical and Electronic Engineering', 'Lab manuals and report formats for the current trimester.', 'public'],
            ['Thesis Writing Circle', 'community', null, 'We read each other drafts and meet once a fortnight.', 'public'],
        ];

        $owners = collect($people)->values();

        foreach ($rows as $i => [$name, $type, $department, $description, $visibility]) {
            $group = CommunityGroup::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'group_type' => $type,
                'description' => $description,
                'department' => $department,
                'visibility' => $visibility,
                'owner_id' => $owners[$i % $owners->count()]->id,
            ]);

            $this->backdate($group, now()->subDays(random_int(5, 90)));

            // The owner counts as a member, plus a few others.
            $members = collect($people)->shuffle()->take(4)->push($owners[$i % $owners->count()])->unique('id');

            foreach ($members as $member) {
                DB::table('community_group_members')->insertOrIgnore([
                    'group_id' => $group->id,
                    'user_id' => $member->id,
                    'role' => $member->id === $group->owner_id ? 'owner' : 'member',
                    'joined_at' => now()->subDays(random_int(1, 60)),
                ]);
            }

            $this->groupWall($group, $members->values(), $i);

            // Somebody waiting at the door, so the owner has a request to act on.
            if ($visibility === 'private') {
                $outsider = collect($people)->reject(fn ($p) => $members->contains('id', $p->id))->first();

                if ($outsider) {
                    DB::table('community_group_members')->insertOrIgnore([
                        'group_id' => $group->id,
                        'user_id' => $outsider->id,
                        'role' => CommunityGroup::PENDING,
                        'joined_at' => now()->subDays(2),
                    ]);
                }
            }
        }
    }

    /**
     * A few posts on a group's wall. An empty wall makes the group page look
     * broken rather than new, which is the whole reason the feature was
     * rebuilt.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $members
     */
    private function groupWall(CommunityGroup $group, $members, int $seed): void
    {
        $sets = [
            [
                'Quiz 2 is on chapter 4 and 5 only. Confirmed with the teacher today.',
                'Uploaded my notes from last week in the files section.',
                'Does anyone have the solution for problem 3 of the sheet?',
            ],
            [
                'Registration for the next trimester opens on Sunday.',
                'Routine clash between the two Tuesday sections again. Reported it.',
            ],
            [
                'Draft of chapter 3 is done. Please read before Thursday.',
                'Supervisor wants the dataset section rewritten. I will start tonight.',
                'Meeting moved to 2 PM, room 412.',
            ],
            [
                'If you get a segmentation fault, print the array index before the crash.',
                'Posting my solution to yesterday problem so people can compare approaches.',
            ],
            [
                'Lab report format changed this trimester. Old template will lose marks.',
                'Manual for experiment 5 is in the files.',
            ],
            [
                'We meet Saturday. Bring two printed pages, not a screen.',
                'Reminder that the abstract has a 250 word limit.',
            ],
        ];

        $lines = $sets[$seed % count($sets)];

        foreach ($lines as $j => $body) {
            $post = Post::create([
                'user_id' => $members[$j % $members->count()]->id,
                'group_id' => $group->id,
                'body' => $body,
                'post_type' => 'text',
                // Group posts are read by the membership, never by the feed.
                'visibility' => 'uiu',
                'is_deleted' => false,
            ]);

            $this->backdate($post, now()->subDays($j + 1)->subHours($seed));
        }
    }

    private function events(array $people, array $clubs): void
    {
        $list = array_values($clubs);

        $rows = [
            ['Intra-UIU Programming Contest', 'Contest', 'Five hours, ten problems, and pizza afterwards. Teams of three.', 3, 120, 'Lab 1, Level 4'],
            ['Arduino From Scratch', 'Workshop', 'Build a line follower in one afternoon. Boards are provided.', 6, 40, 'Robotics Lab'],
            ['CV Clinic With Industry Mentors', 'Career', 'Bring a printed CV and leave with it marked up by a hiring manager.', 9, 60, 'Career Services'],
            ['Inter-Department Football Final', 'Sports', 'CSE versus EEE, with the trophy going to the winner.', 12, 300, 'Campus Ground'],
            ['Photo Walk: Old Dhaka', 'Culture', 'We meet at the gate at 7 AM and shoot until noon.', 15, 25, 'Main Gate'],
            ['Parliamentary Debate Open', 'Culture', 'British parliamentary format, open to all departments.', 18, 80, 'Auditorium'],
            ['Machine Learning Reading Group', 'Academic', 'We read one paper a week and argue about it.', 21, 30, 'Room 512'],
            ['Semester Final Study Jam', 'Academic', 'Quiet room, whiteboards, and seniors on hand for questions.', 24, 100, 'Library Hall'],
            ['Startup Pitch Night', 'Career', 'Six teams, five minutes each, judged by alumni founders.', 27, 90, 'Auditorium'],
            ['Freshers Orientation', 'Campus', 'Meet the clubs, find the rooms, get the timetable explained.', 30, 400, 'Auditorium'],
        ];

        foreach ($rows as $i => [$title, $category, $description, $inDays, $capacity, $location]) {
            $start = now()->addDays($inDays)->setTime(10 + ($i % 6), 0);
            $club = $i % 3 === 0 ? null : $list[$i % count($list)];

            $event = Event::create([
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'start_datetime' => $start,
                'end_datetime' => (clone $start)->addHours(3),
                'location' => $location,
                'capacity' => $capacity,
                'organizer_user_id' => $club?->moderator_id ?? $people['office']->id,
                'club_id' => $club?->id,
                'status' => 'published',
            ]);

            foreach (collect($people)->shuffle()->take(random_int(2, 6)) as $person) {
                DB::table('event_registrations')->insertOrIgnore([
                    'event_id' => $event->id,
                    'user_id' => $person->id,
                    'created_at' => now()->subDays(random_int(1, 10)),
                ]);
            }

            foreach (collect($people)->shuffle()->take(random_int(1, 5)) as $person) {
                DB::table('event_interests')->insertOrIgnore([
                    'event_id' => $event->id,
                    'user_id' => $person->id,
                    'created_at' => now()->subDays(random_int(1, 10)),
                ]);
            }
        }
    }

    private function marketplace(array $people): void
    {
        $rows = [
            ['Calculus Early Transcendentals, 8th edition', 'used_books', 600, 'good', 'Clean copy, no highlighting. Covers MAT 101 and 102.'],
            ['Introduction to Algorithms (CLRS)', 'used_books', 1200, 'good', 'Hardcover, spine intact. Used for one semester.'],
            ['Casio fx-991EX ClassWiz', 'calculator', 1800, 'like_new', 'Bought last semester, cover included.'],
            ['Casio fx-100MS', 'calculator', 700, 'used', 'Works fine, a few scratches on the back.'],
            ['Lenovo ThinkPad T480', 'laptop', 32000, 'used', 'i5 8th gen, 16GB RAM, 512GB SSD. Battery holds about four hours.'],
            ['Dell Inspiron 15 charger', 'laptop', 900, 'used', 'Original 65W adapter, spare after I sold the laptop.'],
            ['Cycle helmet, medium', 'bike_accessories', 850, 'like_new', 'Worn twice. Too small for me.'],
            ['Bicycle rear light and lock set', 'bike_accessories', 450, 'used', 'Both work, lock has two keys.'],
            ['Roommate wanted, Bashundhara R/A', 'roommate', 7000, 'used', 'Two rooms, one free from next month. Ten minutes from campus by rickshaw.'],
            ['Seat available, Madani Avenue flat', 'roommate', 6500, 'used', 'Shared room, wifi and gas bill included.'],
            ['Physics tuition, class 9-10', 'tuition', 4000, 'used', 'Three days a week, near campus. I am a final year EEE student.'],
            ['Programming basics tuition (C and Python)', 'tuition', 5000, 'used', 'For first year students. Weekend slots only.'],
            ['Desk lamp with clamp', 'other', 550, 'used', 'Warm light, works well for late nights.'],
            ['Scientific drawing set', 'other', 300, 'like_new', 'Compass, set squares, protractor. Barely used.'],
            ['Acoustic guitar, beginner', 'other', 4500, 'used', 'Nylon strings, small dent on the body. Sounds fine.'],
            ['Lab coat, size M', 'other', 400, 'like_new', 'Washed and pressed, used for one lab course.'],
            ['Wireless mouse', 'other', 600, 'like_new', 'Logitech, with the dongle and a fresh battery.'],
            ['Old Nokia phone for parts', 'other', 0, 'used', 'Screen cracked, board is fine. Take it if you can use it.'],
            ['Statistics for Engineers, 5th edition', 'used_books', 500, 'used', 'Some pencil notes in the margins.'],
            ['Second hand printer, Canon', 'other', 3200, 'used', 'Prints fine, needs a new cartridge.'],
        ];

        $sellers = collect($people)->values();

        foreach ($rows as $i => [$title, $category, $price, $condition, $description]) {
            $item = MarketplaceItem::create([
                'user_id' => $sellers[$i % $sellers->count()]->id,
                'title' => $title,
                'description' => $description,
                'price' => $price,
                'category' => $category,
                'condition' => $condition,
                'location' => ['Campus', 'Bashundhara R/A', 'Madani Avenue', 'Notun Bazar'][$i % 4],
                'status' => $i % 9 === 0 ? 'sold' : 'available',
            ]);

            $this->backdate($item, now()->subDays(random_int(1, 40)));
        }
    }

    private function lostFound(array $people): void
    {
        $rows = [
            ['lost', 'UIU ID card, CSE', 'Blue lanyard, name printed as Tanvir Hasan. Lost somewhere between the canteen and Level 4.', 'Canteen'],
            ['lost', 'Black umbrella', 'Plain black, folding, handle slightly loose.', 'Library'],
            ['found', 'Silver wristwatch', 'Found on a bench outside the auditorium. Describe the brand to claim it.', 'Auditorium'],
            ['found', 'Set of three keys on a red ring', 'Picked up near the bicycle stand.', 'Bicycle stand'],
            ['lost', 'Casio calculator, name written inside the cover', 'fx-991EX, my student ID is written on the back.', 'Lab 3'],
            ['found', 'Spectacles in a brown case', 'Left in Room 512 after the evening class.', 'Room 512'],
            ['lost', 'Blue water bottle', 'Steel, dented at the bottom, sticker of a robot on the side.', 'Campus Ground'],
            ['found', 'Notebook with EEE lab reports', 'Name on the first page is smudged. Come and check if it is yours.', 'EEE Lab'],
            ['lost', 'Black earphones case', 'Small, matte, no name on it. Lost during the programming contest.', 'Lab 1'],
            ['found', 'Library card', 'Found inside a returned book. Handed to the front desk if unclaimed.', 'Library'],
            ['lost', 'Grey hoodie, size L', 'Left on a chair in the canteen on Sunday afternoon.', 'Canteen'],
            ['found', 'USB pen drive, 32GB', 'Left in the lab computer. Contains lab assignments.', 'Lab 2'],
            ['lost', 'Bus pass', 'Route to Uttara. Name printed on the card.', 'Main Gate'],
            ['found', 'Scientific drawing set', 'Found in the corridor on Level 3.', 'Level 3 corridor'],
            ['lost', 'Handwritten notes, MAT 102', 'A stapled bundle, about twenty pages. Final exam is close.', 'Library'],
            ['found', 'Blue cap', 'Left in the auditorium after orientation.', 'Auditorium'],
            ['lost', 'Rain jacket, navy', 'Left hanging outside the lab door.', 'Lab 4'],
            ['found', 'Student ID, Business Administration', 'Found near the stairs on Level 2.', 'Level 2'],
            ['lost', 'Charger cable, USB-C', 'White, with a small ink mark near the plug.', 'Room 421'],
            ['found', 'Pencil case with a cat print', 'Left on the canteen table.', 'Canteen'],
        ];

        $posters = collect($people)->values();
        $items = [];

        foreach ($rows as $i => [$type, $title, $description, $location]) {
            $resolved = $i % 6 === 0;
            $poster = $posters[$i % $posters->count()];

            $item = LostFoundItem::create([
                'user_id' => $poster->id,
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'location' => $location,
                'event_date' => now()->subDays(random_int(1, 25))->toDateString(),
                'status' => $resolved ? 'resolved' : 'open',
                'resolved_at' => $resolved ? now()->subDays(random_int(1, 10)) : null,
                'resolved_by' => $resolved ? $poster->id : null,
            ]);

            $this->backdate($item, now()->subDays(random_int(1, 30)));
            $items[] = $item;
        }

        $this->claims($items, $posters);
    }

    /** A board with no claims on it does not show what the board is for. */
    private function claims(array $items, $posters): void
    {
        $messages = [
            'I think this is mine. The card has my name on the back, and I lost it on Sunday.',
            'I believe I left that there after the evening class. Happy to describe the contents.',
            'That looks like the one I lost near the stairs. Can I come and check?',
            'Pretty sure this is mine, it has a small scratch on the corner.',
            'I lost something very similar the same week. Could we compare?',
        ];

        foreach ($items as $i => $item) {
            if (! $item->isOpen() || $i % 3 !== 0) {
                continue;
            }

            // Anyone but the poster may claim.
            $claimant = $posters->firstWhere(fn ($p) => $p->id !== $item->user_id);

            $claim = LostFoundClaim::create([
                'item_id' => $item->id,
                'user_id' => $claimant->id,
                'message' => $messages[$i % count($messages)],
                'status' => 'pending',
            ]);

            $this->backdate($claim, now()->subDays(random_int(1, 5)));
        }
    }

    private function notices(array $people): void
    {
        $rows = [
            ['Mid term routine published', 'The mid term routine for this trimester is now on the notice board and the portal. Check your section carefully.', 'routine', 'high', true],
            ['Library hours extended', 'From next week the library stays open until 9 PM on weekdays during the exam period.', 'university', 'normal', false],
            ['Registration deadline reminder', 'Course registration closes on the last working day of this month. Late registration carries a fee.', 'academic', 'urgent', true],
            ['Campus closed for public holiday', 'The campus will remain closed on the announced public holiday. Classes resume the following day.', 'university', 'normal', false],
            ['Scholarship application open', 'Merit scholarship applications for the next trimester are open. Forms are available at the registrar office.', 'scholarship', 'high', false],
        ];

        foreach ($rows as $i => [$title, $body, $type, $priority, $urgent]) {
            $notice = Notice::create([
                'title' => $title,
                'body' => $body,
                'notice_type' => $type,
                'priority' => $priority,
                'audience' => 'all',
                'is_urgent' => $urgent,
                'issuer_id' => $people['office']->id,
                'status' => 'published',
            ]);

            $this->backdate($notice, now()->subDays($i * 3 + 1));
        }
    }

    /** @return array<int, Post> */
    private function posts(array $people, array $clubs): array
    {
        $lines = [
            'Anyone else finding the new lab schedule impossible on Tuesdays?',
            'Finished the line follower today. It only fell off the track twice.',
            'Selling my calculus book, details are on the marketplace page.',
            'The canteen has started doing proper tea again. That is all.',
            'Looking for two more people for the programming contest team.',
            'Photo walk on Friday was worth the early start. Got some good frames.',
            'Reminder that registration closes at the end of the month.',
            'Does anyone have last year MAT 102 question papers?',
            'Football final is on the twelfth. Come and make some noise.',
            'Library until 9 PM is a genuinely good decision.',
            'Lost my ID card somewhere near the canteen. Posted it on lost and found.',
            'First CV clinic session was useful. Bring a printed copy, not a phone.',
        ];

        $authors = collect($people)->values();
        $clubList = array_values($clubs);
        $made = [];

        foreach ($lines as $i => $body) {
            $made[] = $post = Post::create([
                'user_id' => $authors[$i % $authors->count()]->id,
                'club_id' => $i % 4 === 0 ? $clubList[$i % count($clubList)]->id : null,
                'body' => $body,
                'post_type' => 'text',
                // Cycles through the three real audiences so the visibility
                // rules have something to act on.
                'visibility' => ['uiu', 'uiu', 'uiu', 'department', 'connections'][$i % 5],
                'is_deleted' => false,
            ]);

            $this->backdate($post, now()->subHours($i * 7 + 1));
        }

        $made[] = $this->richPosts($authors);

        return array_values(array_filter($made));
    }

    /**
     * One of each of the newer post kinds, so they can be looked at without
     * first having to make one. A code snippet, a post with a document
     * attached, and one carrying a link card.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $authors
     */
    private function richPosts($authors): ?Post
    {
        // --- code
        $code = Post::create([
            'user_id' => $authors[4 % $authors->count()]->id,
            'body' => "int main() {\n    int n;\n    scanf(\"%d\", &n);\n\n    // Segfaults when n is 0 - anyone see why?\n    int a[n];\n    for (int i = 0; i <= n; i++) a[i] = i;\n\n    return 0;\n}",
            'post_type' => 'code',
            'code_language' => 'c',
            'visibility' => 'uiu',
            'is_deleted' => false,
        ]);

        $this->backdate($code, now()->subHours(5));

        // --- document. A real file is written so the download button works.
        $path = 'posts/demo-cse2213-week4-notes.txt';

        Storage::disk(PostMedia::PRIVATE_DISK)->put(
            $path,
            "CSE 2213 - Week 4 notes\n\nStacks and queues, worked examples from Sunday's class.\n\n(Sample file created by uiu:demo-content.)\n"
        );

        $withFile = Post::create([
            'user_id' => $authors[1 % $authors->count()]->id,
            'body' => 'Typed up my notes from week 4 if anyone missed the class.',
            'post_type' => 'standard',
            'visibility' => 'uiu',
            'is_deleted' => false,
        ]);

        PostMedia::create([
            'post_id' => $withFile->id,
            'path' => $path,
            'disk' => PostMedia::PRIVATE_DISK,
            'original_name' => 'CSE2213-week4-notes.txt',
            'type' => PostMedia::FILE,
            'mime_type' => 'text/plain',
            'size' => Storage::disk(PostMedia::PRIVATE_DISK)->size($path),
            'position' => 0,
        ]);

        $this->backdate($withFile, now()->subHours(9));

        // --- link. The card is written directly rather than fetched: the
        // seeder must work on a machine with no internet.
        $withLink = Post::create([
            'user_id' => $authors[3 % $authors->count()]->id,
            'body' => 'The university notice about registration is up here: https://www.uiu.ac.bd/',
            'post_type' => 'standard',
            'visibility' => 'uiu',
            'is_deleted' => false,
        ]);

        PostLink::create([
            'post_id' => $withLink->id,
            'url' => 'https://www.uiu.ac.bd/',
            'title' => 'United International University',
            'description' => 'One of the leading private universities in Bangladesh, in Badda, Dhaka.',
            'site_name' => 'uiu.ac.bd',
        ]);

        $this->backdate($withLink, now()->subHours(13));

        return $code;
    }

    /**
     * Something for the moderation screen to show. Without this it is an empty
     * page, and an empty page proves nothing about whether the buttons work, so
     * each kind of report appears both open and already-decided.
     *
     * @param  array<string, User>  $people
     * @param  array<int, Post>  $posts
     */
    private function moderation(array $people, array $posts): void
    {
        $who = collect($people)->values();

        // Highest indexes touched below are $who[4] and $posts[8].
        if ($who->count() < 5 || count($posts) < 9) {
            return;
        }

        // A couple of replies, so there is something to report a comment on.
        $comments = [];

        foreach ([
            [1, 'Tuesdays are the worst of them. Ours runs straight into the lab.'],
            [3, 'buy followers cheap click my profile link now'],
        ] as $i => [$postIndex, $body]) {
            $comments[] = $comment = Comment::create([
                'post_id' => $posts[$postIndex]->id,
                'user_id' => $who[($i + 2) % $who->count()]->id,
                'body' => $body,
                'is_deleted' => false,
            ]);

            $this->backdate($comment, now()->subHours($i * 5 + 2));
        }

        $admin = User::where('role', 'admin')->value('id');

        $decided = [
            'status' => 'resolved',
            'reviewed_by' => $admin,
            'reviewed_at' => now()->subDay(),
            'moderator_note' => 'Checked and handled by the moderation team.',
        ];

        Report::create([
            'reporter_id' => $who[0]->id,
            'post_id' => $posts[2]->id,
            'reason' => 'Spam or advertising',
            'status' => 'open',
        ]);

        Report::create([
            'reporter_id' => $who[1]->id,
            'post_id' => $posts[5]->id,
            'reason' => 'Off topic for this club',
            'status' => 'open',
        ]);

        Report::create([
            'reporter_id' => $who[2]->id,
            'post_id' => $posts[8]->id,
            'reason' => 'Duplicate of an earlier post',
            ...$decided,
        ]);

        CommentReport::create([
            'reporter_id' => $who[0]->id,
            'comment_id' => $comments[1]->id,
            'reason' => 'Spam or advertising',
            'status' => 'open',
        ]);

        CommentReport::create([
            'reporter_id' => $who[3 % $who->count()]->id,
            'comment_id' => $comments[0]->id,
            'reason' => 'Reported by mistake',
            'status' => 'dismissed',
            'reviewed_by' => $admin,
            'reviewed_at' => now()->subDays(2),
            'moderator_note' => 'Nothing wrong with this one.',
        ]);

        ProfileReport::create([
            'reporter_id' => $who[1]->id,
            'reported_user_id' => $who[4 % $who->count()]->id,
            'reason' => 'Pretending to be someone else',
            'details' => 'The department and student ID on this profile do not match the person in the photo.',
            'status' => 'open',
        ]);
    }

    /**
     * The teaching calendar, two trimesters of it, plus the class lists that
     * the course groups are built from.
     *
     * The second trimester is deliberately one that has already finished, and
     * finished recently enough to still be inside its grace period. That is
     * the only way to see the end-of-trimester countdown without waiting three
     * months for one to happen.
     *
     * @param  array<string, User>  $people
     */
    private function academics(array $people): void
    {
        $now = Trimester::create([
            'name' => 'Fall 2026',
            'code' => self::TRIMESTER_CURRENT,
            'starts_on' => now()->subDays(40)->startOfDay(),
            'ends_on' => now()->addDays(50)->startOfDay(),
        ]);

        // Ended 17 days ago, so its groups archive in 3 days.
        $past = Trimester::create([
            'name' => 'Summer 2026',
            'code' => self::TRIMESTER_PAST,
            'starts_on' => now()->subDays(120)->startOfDay(),
            'ends_on' => now()->subDays(17)->startOfDay(),
        ]);

        $cse = ['tanvir', 'mehjabin', 'sabbir', 'ishrat'];
        $eee = ['rafi', 'jubayer'];

        $rows = [
            [$now, 'CSE 2213', 'Data Structures', 'C', 'Sun-Tue', '10:00', '11:20', 'Room 412', 'shahriar', $cse, CommunityGroup::POSTING_STAFF],
            [$now, 'CSE 3411', 'Database Systems', 'A', 'Mon-Wed', '14:00', '15:20', 'Lab 3', 'shahriar', ['tanvir', 'mehjabin', 'anika'], CommunityGroup::POSTING_EVERYONE],
            [$now, 'EEE 2101', 'Electrical Circuits', 'B', 'Sun-Tue', '08:30', '09:50', 'Room 205', 'farzana', $eee, CommunityGroup::POSTING_STAFF],
            [$past, 'CSE 1111', 'Structured Programming', 'D', 'Mon-Wed', '11:30', '12:50', 'Lab 1', 'shahriar', ['tanvir', 'sabbir'], CommunityGroup::POSTING_EVERYONE],
        ];

        foreach ($rows as [$trimester, $code, $title, $sectionName, $pattern, $from, $to, $room, $teacher, $students, $mode]) {
            $section = CourseSection::create([
                'trimester_id' => $trimester->id,
                'course_code' => $code,
                'course_title' => $title,
                'section_name' => $sectionName,
                'schedule_pattern' => $pattern,
                'start_time' => $from,
                'end_time' => $to,
                'room' => $room,
                'seat_limit' => 40,
                'source' => 'demo',
            ]);

            CourseEnrollment::create([
                'section_id' => $section->id,
                'user_id' => $people[$teacher]->id,
                'role' => CourseEnrollment::TEACHER,
                'status' => CourseEnrollment::ACTIVE,
                'source' => 'demo',
            ]);

            foreach ($students as $handle) {
                if (! isset($people[$handle])) {
                    continue;
                }

                CourseEnrollment::create([
                    'section_id' => $section->id,
                    'user_id' => $people[$handle]->id,
                    'role' => CourseEnrollment::STUDENT,
                    'status' => CourseEnrollment::ACTIVE,
                    'source' => 'demo',
                ]);
            }

            $group = GroupSync::section($section->fresh());
            $group->forceFill(['posting_mode' => $mode])->save();

            $this->classContent($group, $people[$teacher], $code);
        }
    }

    /** Slides, a notice, and a word from the teacher on the wall. */
    private function classContent(CommunityGroup $group, User $teacher, string $code): void
    {
        foreach ([
            ['Week 1 - course outline', 'Assessment split, office hours and the reading list.'],
            ['Week 3 - slides', 'Covered in Sunday\'s class. Bring questions on the last two sections.'],
        ] as $i => [$title, $body]) {
            CommunityGroupFile::create([
                'group_id' => $group->id,
                'user_id' => $teacher->id,
                'kind' => CommunityGroupFile::MATERIAL,
                'title' => $code.' '.$title,
                'body' => $body,
                // Seeded material has no file behind it; the row is what the
                // Materials list is for, and uploading a real one is a click.
                'file_path' => null,
                'original_name' => null,
                'size' => 0,
                'created_at' => now()->subDays(20 - $i * 7),
            ]);
        }

        // A group notice is a real notice, so it also lands on the notice
        // board of everybody enrolled - and nobody else.
        $notice = Notice::create([
            'title' => $code.': Quiz 2 moved to Sunday',
            'body' => 'The quiz planned for Thursday has moved to Sunday, same time and room. It covers chapters 4 and 5 only.',
            'notice_type' => 'section',
            'priority' => 'high',
            'is_urgent' => true,
            'audience' => 'group',
            'issuer_id' => $teacher->id,
            'group_id' => $group->id,
            'status' => 'published',
        ]);

        $this->backdate($notice, now()->subDays(3));

        $post = Post::create([
            'user_id' => $teacher->id,
            'group_id' => $group->id,
            'body' => 'Slides for this week are in Materials. Read section 4.2 before the next class.',
            'post_type' => 'text',
            'visibility' => 'uiu',
            'is_deleted' => false,
        ]);

        $this->backdate($post, now()->subDays(2));
    }

    /**
     * Accounts still waiting to be let in. Everyone else the seeder makes is
     * already active, which leaves the approval screen with an empty queue and
     * nothing to demonstrate. One of these has no ID card on file, because that
     * is the case an administrator is supposed to notice.
     */
    private function signups(string $password): void
    {
        $rows = [
            ['ishrat', 'Ishrat Binte Kamal', 'student', [
                'department' => 'Computer Science and Engineering',
                'student_id' => '011202299',
                'year' => 'First year',
            ], 'Web design, Badminton'],
            ['mahmud', 'Mahmudul Karim', 'student', [
                'department' => 'Electrical and Electronic Engineering',
                'student_id' => '011302140',
                'year' => 'First year',
            ], 'Circuits, Chess'],
            ['shirin', 'Shirin Akter', 'faculty', [
                'department' => 'Business Administration',
                'employee_id' => 'EMP-DEMO-41',
            ], 'Marketing, Case studies'],
        ];

        foreach ($rows as $i => [$handle, $name, $role, $extra, $skills]) {
            $user = $this->account($handle, $name, $role, $password, $extra, $skills);

            $user->forceFill([
                'status' => 'pending',
                'is_verified' => false,
                'signup_requested_at' => now()->subDays($i + 1),
            ])->save();

            $this->backdate($user, now()->subDays($i + 1));
        }
    }

    /**
     * Password recovery requests, including one from an identifier that matches
     * nobody - that is the case the review screen has to refuse rather than act
     * on, and it only shows up if the sample data contains one.
     *
     * @param  array<string, User>  $people
     */
    private function recoveries(array $people): void
    {
        $who = collect($people)->values();

        if ($who->isEmpty()) {
            return;
        }

        $first = $who[0];
        $second = $who[1 % $who->count()];

        $rows = [
            [
                'user_id' => $first->id,
                'submitted_identifier' => $first->email,
                'note' => 'Locked out since the holiday. I still have my UIU ID card.',
                'status' => 'pending',
                'ip_address' => '203.0.113.24',
            ],
            [
                'user_id' => null,
                'submitted_identifier' => self::ORPHAN_IDENTIFIER,
                'note' => 'I cannot remember which address I signed up with.',
                'status' => 'pending',
                'ip_address' => '203.0.113.91',
            ],
            [
                'user_id' => $second->id,
                'submitted_identifier' => $second->student_id ?: $second->email,
                'note' => 'Phone was stolen along with the password note in it.',
                'status' => 'approved',
                'review_note' => 'Identity confirmed at the admin office.',
                'reviewed_by' => User::where('role', 'admin')->value('id'),
                'reviewed_at' => now()->subDays(3),
                'ip_address' => '203.0.113.7',
            ],
        ];

        foreach ($rows as $i => $row) {
            $this->backdate(PasswordResetRequest::create($row), now()->subDays($i + 1));
        }
    }
}
