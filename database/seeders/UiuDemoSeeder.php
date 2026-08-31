<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Event;
use App\Models\LostFoundItem;
use App\Models\MarketplaceItem;
use App\Models\Notice;
use App\Models\Post;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Demo content for UIU CampusConnect. People and student IDs are fictional.
 * Records are created with firstOrCreate, so the seeder is safe to re-run.
 */
class UiuDemoSeeder extends Seeder
{
    /** Summer 2026 undergraduate academic calendar. */
    private const CAL = [
        'classes_begin' => '2026-07-07',
        'instalment_1' => '2026-08-11',
        'midterm_start' => '2026-08-22',
        'midterm_end' => '2026-08-29',
        'eid_miladunnabi' => '2026-08-26',
        'janmashtami' => '2026-09-04',
        'withdrawal_last' => '2026-09-09',
        'instalment_2' => '2026-09-15',
        'instalment_3' => '2026-10-06',
        'final_start' => '2026-10-10',
        'final_end' => '2026-10-17',
        'durga_puja' => '2026-10-20',
        'grade_submission' => '2026-10-22',
        'fall_begins' => '2026-10-27',
    ];

    public function run(): void
    {
        $staff = $this->seedStaff();
        $students = $this->seedStudents();
        $clubs = $this->seedClubs($staff, $students);

        $this->seedNotices($staff);
        $this->seedEvents($staff, $clubs);
        $this->seedPosts($staff, $students, $clubs);
        $this->seedMarketplace($students);
        $this->seedLostAndFound($students);

        $this->command?->info('UIU demo content seeded.');
    }

    /** Columns on the users table. */
    private const USER_COLUMNS = [
        'full_name', 'email', 'role', 'authority_level', 'department',
        'designation', 'student_id', 'employee_id', 'year', 'status',
        'avatar_initials', 'is_verified',
    ];

    private function makeUser(array $attrs): User
    {
        // `bio` belongs to user_profiles, not users.
        $bio = $attrs['bio'] ?? null;
        $userAttrs = array_intersect_key($attrs, array_flip(self::USER_COLUMNS));

        $initials = collect(explode(' ', $attrs['full_name']))
            ->filter()
            ->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');

        $user = User::firstOrCreate(
            ['email' => $userAttrs['email']],
            array_merge([
                'password' => Hash::make('demo123'),
                'status' => 'active',
                'avatar_initials' => $initials ?: 'UI',
            ], $userAttrs)
        );

        UserProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['bio' => $bio]
        );

        return $user;
    }

    private function seedStaff(): array
    {
        $rows = [
            'registrar' => [
                'full_name' => 'Registrar Office', 'email' => 'registrar@uiu.ac.bd',
                'role' => 'official', 'authority_level' => 'registrar',
                'designation' => 'Office of the Registrar', 'is_verified' => true,
                'bio' => 'Official notices, registration and academic records.',
            ],
            'exam' => [
                'full_name' => 'Controller of Examinations', 'email' => 'coe@uiu.ac.bd',
                'role' => 'official', 'authority_level' => 'registrar',
                'designation' => 'Controller of Examinations', 'is_verified' => true,
                'bio' => 'Examination schedules, seat plans and results.',
            ],
            'accounts' => [
                'full_name' => 'Accounts Office', 'email' => 'accounts@uiu.ac.bd',
                'role' => 'official', 'authority_level' => 'registrar',
                'designation' => 'Accounts & Finance', 'is_verified' => true,
                'bio' => 'Tuition instalments, waivers and payment support.',
            ],
            'transport' => [
                'full_name' => 'Transport Office', 'email' => 'transport@uiu.ac.bd',
                'role' => 'official', 'authority_level' => 'registrar',
                'designation' => 'Student Transport Service', 'is_verified' => true,
                'bio' => 'Shuttle routes, timings and transport passes.',
            ],
            'library' => [
                'full_name' => 'UIU Library', 'email' => 'library@uiu.ac.bd',
                'role' => 'official', 'authority_level' => 'member',
                'designation' => 'Central Library', 'is_verified' => true,
                'bio' => 'Borrowing, study spaces and digital resources.',
            ],
            'career' => [
                'full_name' => 'Career Services', 'email' => 'career@uiu.ac.bd',
                'role' => 'official', 'authority_level' => 'member',
                'designation' => 'Career Guidance & Placement', 'is_verified' => true,
                'bio' => 'Internships, job fairs and employer connections.',
            ],
            'nahid' => [
                'full_name' => 'Nahid Hossain', 'email' => 'nahid.hossain@cse.uiu.ac.bd',
                'role' => 'faculty', 'authority_level' => 'club_moderator',
                'department' => 'Computer Science and Engineering',
                'designation' => 'Lecturer', 'is_verified' => true, 'employee_id' => 'F-2019-114',
                'bio' => 'Teaches Data Structures and Algorithms. Moderates the Computer Club.',
            ],
            'farhana' => [
                'full_name' => 'Farhana Rahman', 'email' => 'farhana.rahman@cse.uiu.ac.bd',
                'role' => 'faculty', 'authority_level' => 'member',
                'department' => 'Computer Science and Engineering',
                'designation' => 'Assistant Professor', 'is_verified' => true, 'employee_id' => 'F-2016-078',
                'bio' => 'Research interests: machine learning, natural language processing.',
            ],
            'imran' => [
                'full_name' => 'Imran Kabir', 'email' => 'imran.kabir@eee.uiu.ac.bd',
                'role' => 'faculty', 'authority_level' => 'club_moderator',
                'department' => 'Electrical and Electronics Engineering',
                'designation' => 'Senior Lecturer', 'is_verified' => true, 'employee_id' => 'F-2017-052',
                'bio' => 'Power systems and embedded design. Advises the Robotics Club.',
            ],
            'sabrina' => [
                'full_name' => 'Sabrina Akter', 'email' => 'sabrina.akter@sobe.uiu.ac.bd',
                'role' => 'faculty', 'authority_level' => 'club_moderator',
                'department' => 'School of Business and Economics',
                'designation' => 'Assistant Professor', 'is_verified' => true, 'employee_id' => 'F-2018-203',
                'bio' => 'Marketing strategy and consumer behaviour.',
            ],
        ];

        $out = [];
        foreach ($rows as $key => $attrs) {
            $out[$key] = $this->makeUser($attrs);
        }

        return $out;
    }

    private function seedStudents(): array
    {
        $rows = [
            ['full_name' => 'Tanvir Ahmed', 'sid' => '011231045', 'dept' => 'Computer Science and Engineering', 'mail' => 'bscse', 'year' => '4th Year', 'bio' => 'CSE final year. Competitive programming and backend development.'],
            ['full_name' => 'Nusrat Jahan', 'sid' => '011232118', 'dept' => 'Computer Science and Engineering', 'mail' => 'bscse', 'year' => '3rd Year', 'bio' => 'Interested in UI/UX and front-end engineering.'],
            ['full_name' => 'Rakibul Hasan', 'sid' => '011233076', 'dept' => 'Computer Science and Engineering', 'mail' => 'bscse', 'year' => '2nd Year', 'bio' => 'Learning Java and Android. Photography on weekends.'],
            ['full_name' => 'Tasnim Rahman', 'sid' => '011232204', 'dept' => 'Computer Science and Engineering', 'mail' => 'bscse', 'year' => '3rd Year', 'bio' => 'Robotics Club member. Loves hardware projects.'],
            ['full_name' => 'Mehedi Hasan', 'sid' => '012231019', 'dept' => 'Electrical and Electronics Engineering', 'mail' => 'bseee', 'year' => '4th Year', 'bio' => 'EEE. Power electronics and renewable energy.'],
            ['full_name' => 'Sadia Islam', 'sid' => '012232087', 'dept' => 'Electrical and Electronics Engineering', 'mail' => 'bseee', 'year' => '3rd Year', 'bio' => 'Circuit design and embedded systems.'],
            ['full_name' => 'Arif Chowdhury', 'sid' => '111231033', 'dept' => 'School of Business and Economics', 'mail' => 'bba', 'year' => '4th Year', 'bio' => 'BBA, Marketing major. Business Club executive.'],
            ['full_name' => 'Maliha Khan', 'sid' => '111232156', 'dept' => 'School of Business and Economics', 'mail' => 'bba', 'year' => '2nd Year', 'bio' => 'Finance track. Debate and public speaking.'],
            ['full_name' => 'Shakib Al Amin', 'sid' => '021231008', 'dept' => 'Civil Engineering', 'mail' => 'bsce', 'year' => '3rd Year', 'bio' => 'Civil Engineering. Structural design enthusiast.'],
            ['full_name' => 'Ayesha Siddika', 'sid' => '051232042', 'dept' => 'Pharmacy', 'mail' => 'bpharm', 'year' => '3rd Year', 'bio' => 'Pharmacy student. Volunteers with the Social Service Club.'],
            ['full_name' => 'Fahim Reza', 'sid' => '061231071', 'dept' => 'English', 'mail' => 'bass', 'year' => '4th Year', 'bio' => 'English literature. Writes for the Literature Forum.'],
            ['full_name' => 'Samiha Noor', 'sid' => '071232129', 'dept' => 'Economics', 'mail' => 'bss', 'year' => '2nd Year', 'bio' => 'Economics. Junior Economists Forum member.'],
        ];

        $out = [];
        foreach ($rows as $r) {
            $out[$r['sid']] = $this->makeUser([
                'full_name' => $r['full_name'],
                'email' => $r['sid'].'@'.$r['mail'].'.uiu.ac.bd',
                'role' => 'student',
                'department' => $r['dept'],
                'student_id' => $r['sid'],
                'year' => $r['year'],
                'bio' => $r['bio'],
            ]);
        }

        return array_values($out);
    }

    private function seedClubs(array $staff, array $students): array
    {
        $rows = [
            ['UIU Computer Club', 'Technology', 'Programming contests, workshops and tech talks covering software, networking and web development.', 'nahid', 'Tuesday, 5:00 PM', 'Lab 631'],
            ['UIU Robotics Club', 'Technology', 'Robotics research, builds and national competitions. Founded 2016.', 'imran', 'Thursday, 4:00 PM', 'Robotics Lab'],
            ['UIU App Forum', 'Technology', 'Turning app ideas into working digital solutions, from prototype to launch.', 'nahid', 'Sunday, 6:00 PM', 'Room 512'],
            ['UIU Electrical & Electronics Club', 'Technology', 'Hands-on projects and seminars that sharpen engineering skills. Founded 2013.', 'imran', 'Monday, 4:30 PM', 'EEE Lab 2'],
            ['UIU Business Club', 'Business', 'Developing professional and entrepreneurial potential through case competitions and industry sessions. Founded 2012.', 'sabrina', 'Wednesday, 5:00 PM', 'Room 402'],
            ['UIU Marketing Forum', 'Business', 'An exclusive network for marketing students - brand challenges, guest talks and campaigns.', 'sabrina', 'Sunday, 4:00 PM', 'Room 405'],
            ['UIU Entrepreneur Development Forum', 'Business', 'Encouraging students to think big and build ventures while still on campus.', 'sabrina', 'Thursday, 5:30 PM', 'Innovation Hub'],
            ['UIU Debate Club', 'Cultural', 'Debate training and competition in Bangla and English. Founded 2004.', 'nahid', 'Monday, 5:00 PM', 'Auditorium Annex'],
            ['UIU Cultural Club', 'Cultural', 'The largest club at UIU - music, dance and performance training, and the campus festivals.', 'sabrina', 'Wednesday, 4:00 PM', 'Multipurpose Hall'],
            ['UIU Photography Club', 'Cultural', 'Photo walks, exhibitions and workshops for students behind the lens. Founded 2007.', 'nahid', 'Friday, 3:00 PM', 'Studio, Level 4'],
            ['UIU Theater and Film Club', 'Cultural', 'One of the oldest clubs at UIU, training students in acting and filmmaking.', 'sabrina', 'Saturday, 4:00 PM', 'Multipurpose Hall'],
            ['UIU Sports Club', 'Sports', 'Runs university sports activities, inter-department tournaments and the annual sports fest.', 'imran', 'Friday, 4:00 PM', 'Sports Complex'],
            ['UIU Social Service Club', 'Service', 'Voluntary social service, blood donation drives and community outreach. Founded 2011.', 'sabrina', 'Tuesday, 3:30 PM', 'Room 208'],
            ['UIU Science Forum', 'Academic', 'Cultivating interest in physics, chemistry and mathematics through seminars and olympiads. Founded 2006.', 'farhana', 'Sunday, 3:00 PM', 'Room 610'],
            ['UIU English Language Forum', 'Academic', 'Workshops and seminars that build English proficiency and confidence.', 'farhana', 'Monday, 3:00 PM', 'Room 305'],
            ['Model United Nations Club', 'Academic', 'Leadership and diplomacy through national and international MUN conferences.', 'farhana', 'Thursday, 6:00 PM', 'Conference Room'],
        ];

        $clubs = [];
        foreach ($rows as [$name, $category, $description, $modKey, $meetTime, $meetPlace]) {
            $club = Club::firstOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($name)],
                [
                    'name' => $name,
                    'category' => $category,
                    'description' => $description,
                    'moderator_id' => $staff[$modKey]->id,
                    'meeting_time' => $meetTime,
                    'meeting_place' => $meetPlace,
                    'status' => 'active',
                ]
            );

            // The moderator is always a member; the student slice is deterministic per club.
            if (! $club->members()->where('users.id', $staff[$modKey]->id)->exists()) {
                $club->members()->attach($staff[$modKey]->id, ['club_role' => 'moderator', 'can_post_events' => true]);
            }

            $take = 3 + (crc32($club->slug) % 6);
            foreach (array_slice($students, 0, $take) as $i => $student) {
                if (! $club->members()->where('users.id', $student->id)->exists()) {
                    $club->members()->attach($student->id, [
                        'club_role' => $i === 0 ? 'executive' : 'member',
                        'can_post_events' => $i === 0,
                    ]);
                }
            }

            $clubs[$club->slug] = $club;
        }

        return $clubs;
    }

    private function seedNotices(array $staff): void
    {
        $d = fn (string $key) => Carbon::parse(self::CAL[$key]);
        $mid = $d('midterm_start')->format('F j');
        $midEnd = $d('midterm_end')->format('F j, Y');

        $rows = [
            [
                'title' => 'Mid-Term Examination Seat Plan - Summer 2026',
                'body' => "The seat plan for the Summer 2026 mid-term examinations ({$mid}-{$midEnd}) has been published on UCAM. Students must check their room and seat number before reporting to campus.\n\nBring your UIU ID card to every examination. Entry will not be permitted without it. Report to your assigned room at least 20 minutes before the scheduled start time. Mobile phones, smart watches and any programmable device must be switched off and handed to the invigilator.",
                'type' => 'exam', 'priority' => 'urgent', 'urgent' => true, 'issuer' => 'exam',
                'days_ago' => 3, 'semester' => 'Summer 2026',
            ],
            [
                'title' => 'Second Instalment Payment Deadline - 15 September',
                'body' => "The last date for payment of the 2nd instalment (70% of total payable tuition) for the Summer 2026 trimester is {$d('instalment_2')->format('l, F j, Y')}.\n\nPayment can be made through the designated bank branches or online via UCAM. Students who fail to pay within the deadline will incur a late fee and may be barred from sitting the final examinations.",
                'type' => 'payment', 'priority' => 'high', 'urgent' => false, 'issuer' => 'accounts',
                'days_ago' => 5, 'semester' => 'Summer 2026',
            ],
            [
                'title' => 'Last Day of Course Withdrawal - 9 September',
                'body' => "Students intending to withdraw from any course in the Summer 2026 trimester must complete the process by {$d('withdrawal_last')->format('l, F j, Y')}.\n\nWithdrawal after the mid-term carries a 'W' grade, which does not affect CGPA but does count toward attempted credits. Consult your academic advisor before submitting a withdrawal request.",
                'type' => 'academic', 'priority' => 'high', 'urgent' => false, 'issuer' => 'registrar',
                'days_ago' => 6, 'semester' => 'Summer 2026',
            ],
            [
                'title' => 'Holiday Notice - Eid-e-Miladunnabi',
                'body' => "The university will remain closed on {$d('eid_miladunnabi')->format('l, F j, Y')} on the occasion of Eid-e-Miladunnabi.\n\nExaminations scheduled on that date will be rearranged; the revised schedule will be published on UCAM. All administrative offices will reopen the following working day.",
                'type' => 'university', 'priority' => 'normal', 'urgent' => false, 'issuer' => 'registrar',
                'days_ago' => 4,
            ],
            [
                'title' => 'Library Extended Hours During Mid-Term Week',
                'body' => "The Central Library will remain open until 9:00 PM from {$mid} through {$midEnd} to support students during the mid-term examinations.\n\nGroup study rooms on Level 5 can be reserved for up to two hours at a time at the circulation desk. Please keep noise to a minimum in the silent reading zone.",
                'type' => 'university', 'priority' => 'normal', 'urgent' => false, 'issuer' => 'library',
                'days_ago' => 2,
            ],
            [
                'title' => 'Shuttle Service Timing During Examination Period',
                'body' => "An additional shuttle will operate on the Notun Bazar-UIU route during the mid-term examination period to accommodate morning examination schedules.\n\nRegular pickup points remain Notun Bazar, Family Bazar, Sayed Nagar and 10 Tala. Students are requested to be at their pickup point five minutes before the scheduled departure time, as buses will not wait beyond the published timing.",
                'type' => 'transport', 'priority' => 'normal', 'urgent' => false, 'issuer' => 'transport',
                'days_ago' => 7,
            ],
            [
                'title' => 'Final Examination Schedule - Summer 2026',
                'body' => "The final examinations for the Summer 2026 trimester will be held from {$d('final_start')->format('F j')} to {$d('final_end')->format('F j, Y')}. Classes will remain suspended from {$d('final_start')->copy()->subDays(3)->format('F j')} to prepare for the examinations.\n\nThe detailed date-wise schedule will be published on UCAM two weeks before the examinations begin.",
                'type' => 'exam', 'priority' => 'high', 'urgent' => false, 'issuer' => 'exam',
                'days_ago' => 9, 'semester' => 'Summer 2026',
            ],
            [
                'title' => 'Career Fair 2026 - Employer Registration Open',
                'body' => "Career Services is hosting the UIU Career Fair 2026 in the Multipurpose Hall next month, with participating organisations from the technology, banking, FMCG and telecom sectors.\n\nFinal-year and recently graduated students should prepare an updated CV. CV review sessions will run in the two weeks before the fair - book a slot through Career Services.",
                'type' => 'university', 'priority' => 'normal', 'urgent' => false, 'issuer' => 'career',
                'days_ago' => 8,
            ],
            [
                'title' => 'CSE Department - Lab Access During Mid-Terms',
                'body' => "Computer labs in the CSE department will operate on a restricted schedule during the mid-term examination week. Labs 631 and 632 will remain open for project work from 2:00 PM to 6:00 PM daily.\n\nStudents working on Final Year Design Project deliverables should coordinate with their supervisors for after-hours access.",
                'type' => 'department', 'priority' => 'normal', 'urgent' => false, 'issuer' => 'nahid',
                'days_ago' => 1, 'department' => 'Computer Science and Engineering', 'semester' => 'Summer 2026',
            ],
            [
                'title' => 'Fall 2026 Trimester Begins 27 October',
                'body' => "Classes for the Fall 2026 trimester will begin on {$d('fall_begins')->format('l, F j, Y')}. Course advising and registration dates will be announced after the publication of Summer 2026 results.\n\nStudents are advised to clear all outstanding dues before registration opens, as the system will block advising for accounts with pending balances.",
                'type' => 'academic', 'priority' => 'normal', 'urgent' => false, 'issuer' => 'registrar',
                'days_ago' => 10, 'semester' => 'Fall 2026',
            ],
            [
                'title' => 'Scholarship Applications - Merit and Need-Based',
                'body' => "Applications for merit-based and need-based scholarships for the Fall 2026 trimester are now being accepted.\n\nMerit scholarships are awarded on trimester CGPA with a minimum credit load. Need-based assistance requires supporting documentation of family income. Submit applications to the Accounts Office.",
                'type' => 'scholarship', 'priority' => 'normal', 'urgent' => false, 'issuer' => 'accounts',
                'days_ago' => 12,
            ],
            [
                'title' => 'Class Routine Update - Make-Up Saturday Classes',
                'body' => "To compensate for scheduled holidays during the Summer 2026 trimester, make-up classes will be held on selected Saturdays following the regular weekday routine.\n\nThe next make-up day follows the Wednesday routine. Check UCAM for the specific pattern each make-up day follows, as it differs between dates.",
                'type' => 'routine', 'priority' => 'normal', 'urgent' => false, 'issuer' => 'registrar',
                'days_ago' => 14, 'semester' => 'Summer 2026',
            ],
        ];

        foreach ($rows as $r) {
            $notice = Notice::firstOrCreate(
                ['title' => $r['title']],
                [
                    'body' => $r['body'],
                    'notice_type' => $r['type'],
                    'priority' => $r['priority'],
                    'is_urgent' => $r['urgent'],
                    'audience' => 'all',
                    'department' => $r['department'] ?? null,
                    'semester' => $r['semester'] ?? null,
                    'issuer_id' => $staff[$r['issuer']]->id,
                    'status' => 'published',
                ]
            );

            $this->backdate($notice, $r['days_ago']);
        }
    }

    private function seedEvents(array $staff, array $clubs): void
    {
        $rows = [
            ['UIU Intra-University Programming Contest', 'Competition', 'A five-hour individual contest open to all departments. Problems range from beginner implementation to advanced graph and dynamic programming. Laptops provided; bring your student ID.', 12, 10, 16, 'Lab 631-634', 150, 'uiu-computer-club', 'nahid'],
            ['Hack the Campus 2026', 'Competition', 'A 36-hour hackathon building solutions for campus problems - transport, scheduling, accessibility and student services. Teams of up to four. Meals and workspace provided overnight.', 26, 9, 21, 'Innovation Hub', 200, 'uiu-computer-club', 'nahid'],
            ['Robotics Workshop: Line Following Robots', 'Workshop', 'Hands-on session covering sensor calibration, PID tuning and motor control. Components supplied. No prior robotics experience required, but basic C knowledge helps.', 18, 14, 17, 'Robotics Lab', 40, 'uiu-robotics-club', 'imran'],
            ['CV Writing and Interview Skills', 'Career', 'Career Services walks through CV structure, common mistakes and how to answer behavioural interview questions. Bring a printed draft CV for one-to-one review.', 9, 11, 13, 'Room 402', 80, null, 'career'],
            ['UIU Cultural Night 2026', 'Cultural', 'An evening of music, dance and drama performances from across the university, closing with a live band set. Open to all students, faculty and families.', 33, 18, 22, 'Multipurpose Hall', 500, 'uiu-cultural-club', 'sabrina'],
            ['Inter-Department Football Tournament', 'Sports', 'Group stage fixtures for the inter-department football tournament. Squad lists must be submitted to the Sports Club one week before the opening match.', 21, 15, 19, 'Sports Complex', 300, 'uiu-sports-club', 'imran'],
            ['Photo Walk: Old Dhaka', 'Cultural', 'A guided morning photo walk through Old Dhaka with the Photography Club. Transport arranged from campus. Any camera welcome, including phone cameras.', 16, 7, 12, 'Meet at Main Gate', 30, 'uiu-photography-club', 'nahid'],
            ['Blood Donation Camp', 'Service', 'Annual blood donation drive organised with a partner blood bank. Donors receive a free blood group test and refreshments. Please eat before donating.', 6, 10, 16, 'Ground Floor Lobby', 120, 'uiu-social-service-club', 'sabrina'],
            ['Startup Pitch Night', 'Business', 'Student teams pitch ventures to a panel of founders and investors. Ten minutes to pitch, five for questions. Open to spectators.', 29, 17, 20, 'Auditorium', 180, 'uiu-entrepreneur-development-forum', 'sabrina'],
            ['MUN Delegate Training Session', 'Academic', 'Preparation session covering rules of procedure, position paper writing and caucusing ahead of the national conference.', 13, 16, 19, 'Conference Room', 50, 'model-united-nations-club', 'farhana'],
        ];

        foreach ($rows as [$title, $category, $description, $daysAhead, $startHour, $endHour, $location, $capacity, $clubSlug, $organiser]) {
            $start = Carbon::today()->addDays($daysAhead)->setTime($startHour, 0);
            $end = $daysAhead === 26
                ? $start->copy()->addDay()->setTime($endHour, 0)   // overnight hackathon
                : $start->copy()->setTime($endHour, 0);

            Event::firstOrCreate(
                ['title' => $title],
                [
                    'description' => $description,
                    'category' => $category,
                    'start_datetime' => $start,
                    'end_datetime' => $end,
                    'location' => $location,
                    'capacity' => $capacity,
                    'organizer_user_id' => $staff[$organiser]->id,
                    'club_id' => $clubSlug ? ($clubs[$clubSlug]->id ?? null) : null,
                    'status' => 'published',
                ]
            );
        }
    }

    private function seedPosts(array $staff, array $students, array $clubs): void
    {
        // [author, body, hoursAgo, reactorCount]
        $rows = [
            [$students[0], "Seat plan is up on UCAM. Room 704 for me - checked twice because last trimester I walked into the wrong building and lost ten minutes panicking.\n\nGood luck everyone, see you on the other side of next week.", 2, 9],
            [$staff['nahid'], "Reminder for my Data Structures sections: the mid-term covers everything through balanced trees. Hashing is NOT included - that starts after the break.\n\nConsultation hours tomorrow 2-4 PM in my room if you want to go through past questions.", 4, 22],
            [$students[1], 'Library till 9 PM this week is genuinely the best decision the administration has made all trimester. Level 5 group rooms are already fully booked though - get there early.', 6, 14],
            [$students[4], 'Anyone from EEE has the Electrical Machines lab manual soft copy? Lost mine somewhere between the lab and the cafeteria and the exam is Tuesday.', 9, 3],
            [$students[6], "Business Club case competition registrations close Thursday. We already have fourteen teams which is the most we've had.\n\nIf you have been putting it off, this is the reminder.", 13, 11],
            [$students[2], "Photo walk in Old Dhaka with the Photography Club was unreal. Left campus at 6 AM half asleep and came back with the best shots I've taken.\n\nFull album going up on the club page this weekend.", 20, 27],
            [$staff['career'], "CV review slots for next month's Career Fair are open now. We are running one-to-one sessions in the two weeks before the fair.\n\nFinal year students especially - do not leave this to the last week, the slots go fast.", 26, 31],
            [$students[3], 'Robotics Lab is open for project work 2-6 PM during mid-terms. Our line follower finally completed the track without going off at the second turn. Small victories.', 30, 18],
            [$students[7], 'Does anyone know if the 10 Tala pickup is still running at 9:25 or did it shift? Waited fifteen minutes this morning and gave up.', 34, 5],
            [$staff['transport'], "Clarification on the morning shuttle: the Notun Bazar route runs 07:30-08:45, then 09:25, 10:45, 12:05, 01:25 and 02:45. Pickup points are Notun Bazar, Family Bazar, Sayed Nagar and 10 Tala.\n\nAn extra bus is running during the examination period. Please be at your point five minutes early - buses cannot wait.", 36, 44],
            [$students[9], "Blood donation camp next month - we are looking for volunteers to help with registration and refreshments, not just donors.\n\nIt is about three hours of your time and genuinely makes the day run.", 44, 16],
            [$students[5], 'Circuit simulation assignment took me nine hours and the answer was a single wrong resistor value. Posting this so the next person checks their values first.', 50, 21],
            [$staff['farhana'], "For students asking about research assistant opportunities: we take on undergraduates from third year onward, mostly for data annotation and literature review to start.\n\nEmail me with your CGPA, relevant coursework and what you actually want to learn. Interest matters more than grades.", 58, 39],
            [$students[10], 'Literature Forum is putting together the trimester anthology. Submissions close after mid-terms - poetry, short fiction, essays, Bangla or English. Send them in.', 67, 8],
            [$students[8], 'Structural analysis mid-term tomorrow and I have accepted that the moment distribution method and I will never be friends. Wish me luck.', 72, 12],
            [$students[11], "Junior Economists Forum session on inflation and monetary policy was much better attended than I expected - about forty people in Room 610.\n\nRecording will be shared with members.", 80, 13],
            [$staff['library'], "A reminder that Level 5 group study rooms can be reserved for two hours at a time at the circulation desk. We have had complaints about groups holding rooms all day.\n\nAlso, the silent zone is silent. This includes phone calls in the stairwell.", 90, 25],
            [$students[0], 'Finally got the campus WiFi to work reliably in Lab 632. Turns out I had been connecting to the wrong SSID for two trimesters. Two trimesters.', 100, 34],
        ];

        foreach ($rows as [$author, $body, $hoursAgo, $reactors]) {
            $post = Post::firstOrCreate(
                ['user_id' => $author->id, 'body' => $body],
                ['post_type' => 'standard', 'media_type' => 'none', 'visibility' => 'uiu']
            );

            $this->backdate($post, null, $hoursAgo);

            // Deterministic reactions from the student pool.
            $pool = array_merge($students, [$staff['nahid'], $staff['farhana'], $staff['imran']]);
            foreach (array_slice($pool, 0, min($reactors, count($pool))) as $u) {
                if ($u->id === $author->id) {
                    continue;
                }
                $post->reactions()->firstOrCreate(['user_id' => $u->id], ['reaction' => 'like']);
            }
        }
    }

    private function seedMarketplace(array $students): void
    {
        $rows = [
            ['Data Structures & Algorithms in C++ (3rd ed.)', 'used_books', 850, 'Used one trimester, no highlighting or notes. Cover has minor shelf wear. Covers everything up to graphs.', 'good', 0, 'Library, Level 3'],
            ['Casio fx-991EX Classwiz', 'calculator', 1450, 'Allowed in all UIU examinations. Bought new last year, barely used since I switched to a programmable one for lab work. Slip cover included.', 'good', 1, 'CSE Building'],
            ['HP Pavilion 14 - i5 11th gen, 8GB, 512GB SSD', 'laptop', 42000, 'Three years old, battery still holds about four hours. Fresh Windows install. Fine for coursework and light development, not for heavy gaming. Charger included.', 'used', 4, 'Cafeteria'],
            ['Engineering Mechanics - Hibbeler', 'used_books', 900, 'Statics and dynamics combined edition. Some pencil annotations in the first four chapters, easily erased.', 'good', 8, 'Civil Building'],
            ['Roommate wanted - Bashundhara R/A', 'roommate', 7500, 'Two-bed flat, sharing with one other UIU student. Rent is per person including utilities. Fifteen minutes from campus by rickshaw. Non-smoker preferred.', 'used', 6, 'Bashundhara R/A'],
            ['Physics tuition - HSC level', 'tuition', 4000, 'Offering HSC physics tuition, three days a week. I am a fourth-year EEE student and have taught two batches previously. Monthly rate, negotiable for group sessions.', 'used', 4, 'Online / Badda'],
            ['Bicycle - Duranta single speed', 'bike_accessories', 6500, 'Two years old, recently serviced with new brake pads and tyres. Good for the campus-to-Bashundhara run. Lock included.', 'used', 2, 'Main Gate'],
            ['Scientific drawing set + drafting board', 'other', 1200, 'Complete set for Engineering Drawing - compass, set squares, scale ruler and A2 board. Used for one course only.', 'good', 8, 'Civil Building'],
            ['Principles of Marketing - Kotler & Armstrong', 'used_books', 1100, 'Latest edition available in the campus bookshop for nearly triple this. Clean copy, no markings.', 'good', 6, 'SoBE Floor'],
            ['Mechanical keyboard - Redragon Kumara', 'other', 2200, 'Red switches, white backlight, detachable cable. Selling because I moved to a full-size layout. Keycaps cleaned.', 'good', 1, 'Lab 631'],
            ['Lab coat (size M) - Pharmacy', 'other', 550, 'Standard white lab coat, worn for one trimester of practicals. Washed and pressed.', 'good', 9, 'Pharmacy Lab'],
        ];

        foreach ($rows as [$title, $category, $price, $description, $condition, $sellerIndex, $location]) {
            $item = MarketplaceItem::firstOrCreate(
                ['title' => $title],
                [
                    'user_id' => $students[$sellerIndex]->id,
                    'description' => $description,
                    'price' => $price,
                    'category' => $category,
                    'condition' => $condition,
                    'location' => $location,
                    'status' => 'available',
                ]
            );

            $this->backdate($item, null, 6 + (crc32($title) % 160));
        }
    }

    private function seedLostAndFound(array $students): void
    {
        $rows = [
            ['lost', 'Black Casio fx-991EX with name sticker', 'Left it on the desk in Room 704 after the Discrete Mathematics class on Tuesday afternoon. My name is written on a sticker on the back of the slip cover.', 'Room 704', 0, 3],
            ['found', 'UIU ID card - CSE student', 'Found near the Level 3 staircase in the CSE building. Handed nothing in yet because the office was closed. Describe the ID number to claim.', 'CSE Building, Level 3', 2, 1],
            ['lost', 'Blue Jansport backpack', 'Left in the cafeteria around 1:30 PM on Sunday. Contains an Engineering Drawing board and a grey water bottle. The board matters more than the bag.', 'Cafeteria', 8, 2],
            ['found', 'Set of keys with a red keychain', 'Three keys on a ring with a small red plastic keychain, found on a bench outside the Multipurpose Hall.', 'Outside Multipurpose Hall', 5, 4],
            ['lost', 'Prescription glasses in a black case', 'Thin black frames in a hard black case. Probably dropped between the Main Gate and the library. I genuinely cannot read the board without them.', 'Main Gate / Library', 9, 5],
            ['found', 'Wireless earbuds case (white)', 'Found in the shuttle on the Notun Bazar route, morning trip. No earbuds inside, just the charging case. Handed to me by the driver.', 'Shuttle - Notun Bazar route', 6, 6],
            ['lost', 'Lab notebook - Electrical Machines', 'Green hardcover notebook with all my lab readings for this trimester. Lost somewhere between the EEE lab and the shuttle stop. Reward offered, I am desperate.', 'EEE Lab / Shuttle stop', 4, 2],
            ['found', 'Grey hoodie, size L', 'Left on a chair in the library silent zone on Level 5 for two days. Brought it to the circulation desk area.', 'Library, Level 5', 1, 7],
        ];

        foreach ($rows as [$type, $title, $description, $location, $ownerIndex, $daysAgo]) {
            $item = LostFoundItem::firstOrCreate(
                ['title' => $title],
                [
                    'user_id' => $students[$ownerIndex]->id,
                    'type' => $type,
                    'description' => $description,
                    'location' => $location,
                    'event_date' => Carbon::today()->subDays($daysAgo),
                    'status' => 'open',
                ]
            );

            $this->backdate($item, $daysAgo);
        }
    }

    /** Move a record's timestamps into the past so listings have a spread of dates. */
    private function backdate($model, ?int $days = null, ?int $hours = null): void
    {
        $when = $hours !== null
            ? Carbon::now()->subHours($hours)
            : Carbon::now()->subDays($days ?? 0);

        $model->created_at = $when;
        $model->updated_at = $when;
        $model->saveQuietly();
    }
}
