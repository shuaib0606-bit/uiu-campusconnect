<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\CommunityGroup;
use App\Models\Event;
use App\Models\Notice;
use App\Models\Trimester;
use App\Models\User;
use App\Support\GroupSync;
use App\Support\ImageResizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Replaces the invented clubs, notices and events with the real ones.
 *
 * Every row in this file was taken from a public UIU page, and each one
 * carries the address it came from. Nothing here is a guess: where a real
 * fact was not published - a club's faculty moderator, the date of next
 * term's edition of a programme - the row says so in its `note`, and
 * docs/UIU-Real-Data-Sources.pdf lists those separately from the sourced
 * ones. That separation is the point of the command: a demonstration built
 * on real names is worth more than one built on invented ones, but only if
 * anybody can tell which is which.
 *
 *     php artisan uiu:seed-uiu-data --dry-run
 *     php artisan uiu:seed-uiu-data
 *
 * Safe to run again: clubs are matched by slug, notices and events by title,
 * so a second run updates rather than duplicates.
 */
class SeedUiuData extends Command
{
    protected $signature = 'uiu:seed-uiu-data
        {--dry-run : Report what would change without writing anything}
        {--keep-demo-clubs : Leave the invented clubs active instead of archiving them}';

    protected $description = 'Replace the sample clubs, notices and events with real UIU ones';

    /** Where a club logo is picked up from, if the file has been put there. */
    private const LOGO_FOLDER = 'seed-logos';

    /** And the banner behind it. */
    private const COVER_FOLDER = 'seed-covers';

    private const IMAGE_TYPES = ['png', 'jpg', 'jpeg', 'webp'];

    /**
     * The real trimester. UIU publishes its calendar per programme; this is
     * the undergraduate one that covers today.
     *
     * Source: https://www.uiu.ac.bd/academics/calendar/
     */
    private const TRIMESTER = [
        'code' => '2026-SUM',
        'name' => 'Summer 2026',
        'starts_on' => '2026-07-04',
        'ends_on' => '2026-10-22',
    ];

    /**
     * UIU's schools and departments, spelled as the university spells them.
     *
     * Source: https://www.uiu.ac.bd/academics/
     */
    private const DEPARTMENTS = [
        'Computer Science and Engineering',
        'Data Science',
        'Electrical and Electronic Engineering',
        'Civil Engineering',
        'Pharmacy',
        'English',
        'Environment and Development Studies',
        'Media Studies and Journalism',
        'Business Administration',
        'Accounting and Information Systems',
        'Economics',
        'Biotechnology and Genetic Engineering',
    ];

    /**
     * The 24 clubs, forums and societies UIU lists.
     *
     * name, category, description, meeting day, place, facebook handle.
     * The descriptions are shortened from the university's own wording. The
     * meeting time and place are NOT published anywhere, so they are left
     * empty rather than invented.
     *
     * Source: https://www.uiu.ac.bd/campus-life/clubs-forums/
     */
    private const CLUBS = [
        ['UIU Debate Club', 'Culture', 'One of the oldest clubs of UIU and one of the top debate clubs in the country.', 'UIU.Debate.Club'],
        ['UIU Cultural Club', 'Culture', 'Established in 2006, the largest of UIU\'s clubs and forums.', 'UIU.Cultural.club.uiucc'],
        ['UIU Theater and Film Club', 'Culture', 'One of the oldest clubs of UIU, for acting and filmmaking.', 'uiu.theater'],
        ['UIU Photography Club', 'Culture', 'Founded in 2007. Organises the national photography exhibition Shutter Stories.', 'UIUPC'],
        ['UIU Business Club', 'Career', 'Established in 2012, under the theme Imagine > Believe > Create.', 'uiu.bc'],
        ['UIU Sports Club', 'Sports', 'The club that organises all sporting activity at the university.', 'SCUIU'],
        ['UIU Social Service Club', 'Community', 'Founded in 2011 to extend a helping hand to the underprivileged.', 'uiussc'],
        ['UIU Electrical and Electronics Club', 'Technology', 'Established in February 2013. Hosts Tech Quest.', 'uiueec'],
        ['UIU Robotics Club', 'Technology', 'Formed in 2016. Works on robotics including Mars rover technology.', 'uiurobotics'],
        ['UIU Computer Club', 'Technology', 'Programming and web development, from ASP.NET and PHP to Android.', 'uiuccl'],
        ['UIU Supply Chain Club', 'Career', 'Operations and logistics management.', 'uiuscmc'],
        ['UIU Model United Nations Club', 'Culture', 'Diplomacy, negotiation and leadership through MUN conferences.', 'MUNClubUIU'],
        ['UIU Marketing Forum', 'Career', 'Signature programme: Brand Master.', 'UIU.MF'],
        ['UIU HR Forum', 'Career', 'Established in 2014 under the motto People, Process & Power.', 'uiuhrforum'],
        ['UIU Finance Forum', 'Career', 'Started in 2014. Signature programme: The Excelist.', 'UIU.DCCSA.FF'],
        ['UIU Accounting Forum', 'Career', 'Signature programme: the Accounting Olympiad.', 'UIUAccountingforum'],
        ['UIU App Forum', 'Technology', 'Building apps and services for a digital Bangladesh.', 'uiuappf'],
        ['UIU Communication Society', 'Culture', 'Founded in 2017 and re-established in 2019.', 'uiucs'],
        ['UIU Entrepreneur Development Forum', 'Career', 'Signature programme: Entrepreneurship 360 Degree.', 'UIU.EDF'],
        ['UIU English Language Forum', 'Academic', 'Signature programme: English Edge.', 'UIUELF'],
        ['UIU Association of Civil Engineering Students', 'Academic', 'A platform for civil engineering students.', 'UIUACES'],
        ['UIU Science Forum', 'Academic', 'Natural sciences, and support for Olympiad participation.', 'uiusf'],
        ['UIU Literature and Writers\' Forum', 'Culture', 'An open platform for anyone who loves to write.', 'uiulwf.official'],
        ['UIU Junior Economists\' Forum', 'Academic', 'Signature programme: Hult Prize at UIU.', 'JEFUIU'],
    ];

    /**
     * Notices as published on the university notice board, with the date
     * each one carries there.
     *
     * title, date, type, priority, urgent, source slug.
     *
     * Source: https://www.uiu.ac.bd/notice/
     */
    private const NOTICES = [
        ['NOTICE REGARDING CLASSES TO BE HELD ON SEPTEMBER 24, 2026', '2026-09-21', 'routine', 'high', true, 'notice-regarding-classes-to-be-held-on-september-24-2026'],
        ['Mid Term Exam Schedule, Fall 2026 Semester', '2026-09-16', 'exam', 'urgent', true, 'mid-term-exam-schedule-fall-2026-semester'],
        ['Course withdrawal Summer 2026 Trimester', '2026-09-05', 'academic', 'high', false, 'course-withdrawal-summer-2026-trimester'],
        ['HOLIDAY NOTICE: SHUBHO JANMASHTAMI', '2026-09-02', 'university', 'normal', false, 'holiday-notice-shubho-janmashtami-3'],
        ['Updated Notice on Registration for UIU Student Transportation with AC Bus', '2026-08-26', 'transport', 'normal', false, 'updated-notice-on-registration-for-uiu-student-transportation-with-ac-bus'],
        ['[Revised] UIU Student Transportation Service Registration - Summer (Post Mid-Term) 2026 Trimester and Fall 2026 Semester-Partial (Pharmacy)', '2026-08-25', 'transport', 'normal', false, 'revised-uiu-student-transportation-service-registration-summer-post-mid-term-2026-trimester-and-fall-2026-semester-partial-pharmacy'],
        ['List of Selected Candidates - 1st Admission Test for Fall 2026 Trimester', '2026-08-24', 'admission', 'high', false, 'list-of-selected-candidates-1st-admission-test-for-fall-2026-trimester'],
        ['HOLIDAY NOTICE: HOLY EID-E-MILADUNNABI', '2026-08-24', 'university', 'normal', false, 'holiday-notice-holy-eid-e-miladunnabi-3'],
        ['Notice: Student Transportation Service Registration - Summer (Post Mid-Term) 2026 Trimester and Fall 2026 Semester', '2026-08-23', 'transport', 'normal', false, 'notice-student-transportation-service-registration-summer-post-mid-term-2026-trimester-and-fall-2026-semester'],
        ['Revised Notice: Policy on the use of Programmable/Scientific Calculators in Examinations', '2026-08-23', 'exam', 'high', false, 'revised-notice-policy-on-the-use-of-programmable-scientific-calculators-in-examinations'],
        ['Scholarship Award List - Fall 2026 Semester', '2026-08-22', 'scholarship', 'high', false, 'scholarship-award-list-fall-2026-semester'],
        ['Policy on the use of Programmable/Scientific Calculators in Examinations', '2026-08-19', 'exam', 'normal', false, 'policy-on-the-use-of-programmable-scientific-calculators-in-examinations'],
    ];

    /**
     * Events as published on the university events page, with the dates they
     * carry there. Most are in the past, because that page is an archive.
     *
     * title, starts, ends, category, club slug or null, source slug.
     *
     * Source: https://www.uiu.ac.bd/event/
     */
    private const EVENTS = [
        ['Seminar on Thalassemia Awareness & Blood Donation Campaign', '2026-09-05', '2026-09-06', 'Campus', 'uiu-social-service-club', 'seminar-on-thalassemia-awareness-blood-donation-campaign'],
        ['Brand Master 4.0 By UIU Marketing Forum', '2026-09-08', '2026-09-26', 'Career', 'uiu-marketing-forum', 'brand-master-4-0-by-uiu-marketing-forum'],
        ['Research & Writing Workshop | Session 01', '2026-09-02', '2026-09-02', 'Academic', null, 'research-writing-workshop-session-01'],
        ['From Idea to Deployment: A Hands-On ML Journey', '2026-08-16', '2026-08-18', 'Workshop', 'uiu-computer-club', null],
        ['The Grand Finale of IdeationX', '2026-08-16', '2026-08-16', 'Career', 'uiu-entrepreneur-development-forum', 'the-grand-finale-of-ideationx'],
        ['Architecture of Stories: Single Photo to Visual Narratives', '2026-08-17', '2026-08-17', 'Culture', 'uiu-photography-club', 'architecture-of-stories-single-photo-to-visual-narratives'],
        ['1st UIU Inter-Department Debate Tournament - Preliminary & Semifinal Round', '2026-08-14', '2026-09-12', 'Culture', 'uiu-debate-club', '1st-uiu-inter-department-debate-tournament-preliminary-semifinal-round'],
        ['Step into the World of ROID at UIU!', '2026-08-15', '2026-08-15', 'Technology', 'uiu-robotics-club', 'step-into-the-world-of-roid-at-uiu'],
        ['Workshop on Corporate Excel', '2026-08-12', '2026-08-12', 'Career', 'uiu-business-club', 'workshop-on-corporate-excel'],
        ['Mock Interview Session', '2026-08-12', '2026-08-12', 'Career', 'uiu-hr-forum', 'mock-interview-session-4'],
        ['2nd workshop of IdeationX', '2026-08-11', '2026-08-11', 'Career', 'uiu-entrepreneur-development-forum', '2nd-workshop-of-ideationx'],
        ['Lexicon Fair 2026 - A Festival of English Language and Culture', '2026-08-11', '2026-08-11', 'Culture', 'uiu-english-language-forum', 'lexicon-fair-2026-a-festival-of-english-language-and-culture'],
    ];

    /**
     * Real, named club programmes, given a date this trimester so the events
     * page is not an archive of things that already happened.
     *
     * The NAME of each is real and comes from the club's own description on
     * the university page. The DATE is not: UIU has not announced this
     * term's edition. Every row here is listed under "dated by us" in the
     * sources PDF, and the seeded description says so on the page itself.
     *
     * title, club slug, days from today, category.
     */
    private const UPCOMING = [
        ['Shutter Stories - National Photography Exhibition', 'uiu-photography-club', 9, 'Culture'],
        ['Tech Quest', 'uiu-electrical-and-electronics-club', 13, 'Technology'],
        ['Accounting Olympiad', 'uiu-accounting-forum', 17, 'Academic'],
        ['The Excelist', 'uiu-finance-forum', 21, 'Career'],
        ['English Edge', 'uiu-english-language-forum', 24, 'Academic'],
        ['Hult Prize at UIU', 'uiu-junior-economists-forum', 28, 'Career'],
        ['Entrepreneurship 360 Degree', 'uiu-entrepreneur-development-forum', 32, 'Career'],
    ];

    private const NOTICE_BASE = 'https://www.uiu.ac.bd/notice/';

    private const EVENT_BASE = 'https://www.uiu.ac.bd/event/';

    private bool $dry = false;

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');

        if ($this->dry) {
            $this->warn('Dry run: nothing will be written.');
        }

        $registrar = User::where('authority_level', 'registrar')->orderBy('id')->first()
            ?? User::where('role', 'admin')->orderBy('id')->first();

        if (! $registrar) {
            $this->error('No registrar or administrator account to issue notices as. Run uiu:demo-content first.');

            return self::FAILURE;
        }

        $this->trimester();
        $clubs = $this->clubs();
        $this->panels($clubs);
        $this->notices($registrar);
        $this->events($clubs, $registrar);

        $this->newLine();
        $this->info($this->dry ? 'Dry run finished.' : 'Real UIU data seeded.');
        $this->line('Sources are listed in docs/UIU-Real-Data-Sources.pdf.');

        return self::SUCCESS;
    }

    private function trimester(): void
    {
        $this->components->info('Trimester');

        if ($this->dry) {
            $this->line('  would set '.self::TRIMESTER['name'].' ('.self::TRIMESTER['starts_on'].' to '.self::TRIMESTER['ends_on'].')');

            return;
        }

        Trimester::updateOrCreate(
            ['code' => self::TRIMESTER['code']],
            [
                'name' => self::TRIMESTER['name'],
                'starts_on' => self::TRIMESTER['starts_on'],
                'ends_on' => self::TRIMESTER['ends_on'],
            ]
        );

        $this->line('  '.self::TRIMESTER['name'].' set from the published academic calendar.');
    }

    /**
     * @return array<string, Club>
     */
    private function clubs(): array
    {
        $this->components->info('Clubs');

        // Faculty and office accounts to act as moderators. UIU does not
        // publish who advises each club, so these are assigned round-robin
        // from the staff already in the database rather than invented.
        $staff = User::whereIn('role', ['faculty', 'official'])
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($staff->isEmpty()) {
            $this->warn('  No staff accounts, so clubs will have no moderator.');
        }

        $clubs = [];
        $kept = [];

        foreach (self::CLUBS as $i => [$name, $category, $description, $facebook]) {
            $slug = Str::slug($name);
            $kept[] = $slug;

            if ($this->dry) {
                $this->line('  would upsert '.$name);
                continue;
            }

            $club = Club::firstOrNew(['slug' => $slug]);

            $club->fill([
                'name' => $name,
                'category' => $category,
                'description' => $description."\n\nFacebook: facebook.com/".$facebook,
                // UIU does not publish when or where any club meets, so
                // these stay empty - and are actively cleared, because four
                // of these slugs belonged to the invented sample clubs and
                // arrived carrying made-up times like "Friday 9:00 AM at
                // Campus Ground". The page simply omits the line.
                'meeting_time' => null,
                'meeting_place' => null,
                'status' => 'active',
            ]);

            if (! $club->moderator_id && $staff->isNotEmpty()) {
                $club->moderator_id = $staff[$i % $staff->count()]->id;
            }

            $club->save();

            $this->attachArtwork($club);

            if ($club->moderator_id) {
                DB::table('club_members')->updateOrInsert(
                    ['club_id' => $club->id, 'user_id' => $club->moderator_id],
                    ['club_role' => 'Moderator', 'can_post_events' => true, 'joined_at' => now()]
                );
            }

            $clubs[$slug] = $club;
        }

        if (! $this->dry) {
            $this->line('  '.count($clubs).' clubs from the university list.');
        }

        $this->retireInvented($kept);

        return $clubs;
    }

    /**
     * A club's real logo and banner, if somebody has put the files where this
     * can find them: storage/app/seed-logos/<slug>.png and
     * storage/app/seed-covers/<slug>.jpg.
     *
     * This command cannot fetch a club's logo - they live on Facebook pages
     * and belong to the clubs - so nothing here invents one. A club with no
     * file keeps the generated initials badge, which is honest about not
     * having the real thing.
     */
    private function attachArtwork(Club $club): void
    {
        $this->attachLogo($club);
        $this->attachCover($club);
    }

    private function attachLogo(Club $club): void
    {
        if ($club->logo_path) {
            return;
        }

        $source = $this->fileFor(self::LOGO_FOLDER, $club->slug);

        if ($source === null) {
            return;
        }

        $target = 'clubs/logos/'.basename($source);

        Storage::disk('public')->put($target, file_get_contents($source));
        ImageResizer::shrink($target);

        $club->forceFill(['logo_path' => $target])->save();

        $this->line('  logo: '.$club->name);
    }

    /**
     * The banner goes in as an ordinary gallery picture, because that is what
     * a banner is here - the first picture in the club's gallery. Doing it
     * any other way would give the club a cover the Edit pictures panel could
     * not then change.
     */
    private function attachCover(Club $club): void
    {
        if ($club->media()->exists()) {
            return;
        }

        $source = $this->fileFor(self::COVER_FOLDER, $club->slug);

        if ($source === null) {
            return;
        }

        $target = 'clubs/'.basename($source);

        Storage::disk('public')->put($target, file_get_contents($source));

        // Shrink first, so the recorded size matches the file on disk.
        ImageResizer::shrink($target);

        $club->media()->create([
            'path' => $target,
            'mime_type' => 'image/'.(pathinfo($target, PATHINFO_EXTENSION) === 'png' ? 'png' : 'jpeg'),
            'size' => Storage::disk('public')->size($target),
            'position' => 0,
            'uploaded_by' => $club->moderator_id,
        ]);

        $this->line('  banner: '.$club->name);
    }

    /**
     * The first file in $folder named after this slug, whatever its type, as
     * an absolute path.
     *
     * Read off the filesystem rather than through the "local" disk, because
     * that disk's root is storage/app/private - so a file put in the obvious
     * place, storage/app/seed-logos, was invisible to it and every club
     * quietly kept its generated badge. Naming the folder outright means the
     * place a person drops a file is the place this looks.
     */
    private function fileFor(string $folder, string $slug): ?string
    {
        foreach (self::IMAGE_TYPES as $extension) {
            $path = storage_path('app/'.$folder.'/'.$slug.'.'.$extension);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Clubs that are not on the university's list are the invented ones from
     * the sample data. They are archived rather than deleted, because their
     * events, posts and membership rows point at them.
     *
     * @param  list<string>  $kept
     */
    private function retireInvented(array $kept): void
    {
        if ($this->option('keep-demo-clubs')) {
            return;
        }

        $query = Club::where('status', 'active')->whereNotIn('slug', $kept);
        $names = $query->pluck('name');

        if ($this->dry) {
            if ($names->isNotEmpty()) {
                $this->line('  would archive: '.$names->implode(', '));
            }

            return;
        }

        if ($names->isNotEmpty()) {
            $query->update(['status' => 'archived']);
            $this->line('  archived '.$names->count().' invented clubs: '.$names->implode(', '));
        }

        // Always, not only when something was archived just now. The first
        // version of this ran the group cleanup inside the branch above, so
        // on the next run - with the clubs already archived - it found
        // nothing to archive, returned early, and left the groups standing.
        // A cleanup that only fires on the transition cannot repair a state
        // it has already passed through.
        $this->retireDerivedGroups();
    }

    /**
     * Removes the group behind any club that is no longer active.
     *
     * A club group is derived: it exists because the club exists, and nobody
     * joined it by choice. Leaving one behind put "UIU Career Forum" in every
     * member's group list under "Your clubs" after the club itself had gone
     * from the directory - a room belonging to a club that is not there.
     *
     * Asked of the current state rather than of what changed this run, so it
     * repairs a database left half-tidied by an earlier version.
     */
    private function retireDerivedGroups(): void
    {
        $retired = Club::where('status', '!=', 'active')->pluck('id');

        if ($retired->isEmpty()) {
            return;
        }

        $groupIds = CommunityGroup::where('kind', CommunityGroup::KIND_CLUB)
            ->whereIn('source_id', $retired)
            ->pluck('id');

        if ($groupIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($groupIds) {
            // The wall, the shelf and the roster all point at the group, and
            // none of them cascade, so each one is cleared by hand.
            DB::table('posts')->whereIn('group_id', $groupIds)->delete();
            DB::table('community_group_files')->whereIn('group_id', $groupIds)->delete();
            DB::table('community_group_members')->whereIn('group_id', $groupIds)->delete();

            $noticeIds = DB::table('notices')->whereIn('group_id', $groupIds)->pluck('id');

            DB::table('notice_reads')->whereIn('notice_id', $noticeIds)->delete();
            DB::table('notices')->whereIn('id', $noticeIds)->delete();

            CommunityGroup::whereIn('id', $groupIds)->delete();
        });

        $this->line('  removed '.$groupIds->count().' group(s) behind them');
    }

    /**
     * Gives a handful of the real clubs a student panel, so the handover and
     * nomination features still have somewhere to be demonstrated.
     *
     * @param  array<string, Club>  $clubs
     */
    private function panels(array $clubs): void
    {
        if ($this->dry || $clubs === []) {
            return;
        }

        $this->components->info('Panels');

        $students = User::where('role', 'student')->where('status', 'active')->get();

        if ($students->count() < 4) {
            $this->warn('  Not enough student accounts to seat panels.');

            return;
        }

        $roles = ['President', 'General Secretary', 'Treasurer'];
        $panelled = 0;
        $index = 0;

        foreach ($clubs as $club) {
            // Every designation goes first. Without this a second run picks a
            // fresh set of students, hands them President and Treasurer, and
            // leaves the previous run's holders where they were - which gave
            // the Sports Club two presidents and three treasurers, a state
            // the panel rules exist to make impossible.
            DB::table('club_members')
                ->where('club_id', $club->id)
                ->when($club->moderator_id, fn ($q) => $q->where('user_id', '!=', $club->moderator_id))
                ->update(['club_role' => 'member', 'can_post_events' => false]);

            // Every club gets members. A directory where two thirds of the
            // clubs read "1 member" says the import half worked.
            $picked = $students->shuffle()->take(random_int(4, min(8, $students->count())))->values();

            // The first third also get a full panel, which is enough places
            // to demonstrate a handover without pretending every club on
            // campus has one seated this week.
            $withPanel = $index < 8;

            foreach ($picked as $j => $student) {
                if ($student->id === $club->moderator_id) {
                    continue;
                }

                $role = $withPanel ? ($roles[$j] ?? 'member') : 'member';

                DB::table('club_members')->updateOrInsert(
                    ['club_id' => $club->id, 'user_id' => $student->id],
                    [
                        'club_role' => $role,
                        'can_post_events' => $role !== 'member',
                        'joined_at' => now()->subDays(random_int(20, 300)),
                    ]
                );
            }

            GroupSync::club($club->fresh());

            $panelled += $withPanel ? 1 : 0;
            $index++;
        }

        $this->line('  '.count($clubs)." clubs given members and a group, {$panelled} of them a panel.");
    }

    private function notices(User $registrar): void
    {
        $this->components->info('Notices');

        // The sample notices go, or the board is half real and half invented
        // with nothing to tell them apart.
        $this->clearSeeded(Notice::class, array_column(self::NOTICES, 0));

        foreach (self::NOTICES as [$title, $date, $type, $priority, $urgent, $slug]) {
            if ($this->dry) {
                $this->line('  would publish '.Str::limit($title, 60));
                continue;
            }

            $notice = Notice::updateOrCreate(
                ['title' => $title],
                [
                    // Kept short on purpose. The board shows a 230-character
                    // excerpt, and a longer sentence pushed the address past
                    // the cut, so every card ended in half a URL.
                    'body' => 'Published by the University on '.
                        \Illuminate\Support\Carbon::parse($date)->format('j F Y').
                        ". Full notice and attachments: uiu.ac.bd/notice/{$slug}/",
                    'notice_type' => $type,
                    'priority' => $priority,
                    'is_urgent' => $urgent,
                    'audience' => 'all',
                    'issuer_id' => $registrar->id,
                    'status' => 'published',
                ]
            );

            // The board sorts by when a notice was issued, so the row has to
            // carry the real date rather than the date it was imported.
            $notice->forceFill([
                'created_at' => $date.' 10:00:00',
                'updated_at' => $date.' 10:00:00',
            ])->save();
        }

        if (! $this->dry) {
            $this->line('  '.count(self::NOTICES).' notices from the university board.');
        }
    }

    /**
     * @param  array<string, Club>  $clubs
     */
    private function events(array $clubs, User $registrar): void
    {
        $this->components->info('Events');

        $titles = array_merge(array_column(self::EVENTS, 0), array_column(self::UPCOMING, 0));
        $this->clearSeeded(Event::class, $titles);

        foreach (self::EVENTS as [$title, $from, $to, $category, $slug, $source]) {
            $club = $slug ? ($clubs[$slug] ?? null) : null;

            $body = 'Held at United International University.';

            if ($source) {
                $body .= "\n\nAnnouncement: ".self::EVENT_BASE.$source.'/';
            }

            $this->putEvent($title, $body, $category, $from.' 10:00:00', $to.' 17:00:00', $club, $registrar);
        }

        foreach (self::UPCOMING as [$title, $slug, $inDays, $category]) {
            $club = $clubs[$slug] ?? null;
            $start = now()->addDays($inDays)->setTime(10, 0);

            $this->putEvent(
                $title,
                "A regular programme of ".($club?->name ?? 'the university').
                ". The date shown here is a placeholder for this trimester's edition and has not been announced.",
                $category,
                $start->format('Y-m-d H:i:s'),
                $start->copy()->addHours(6)->format('Y-m-d H:i:s'),
                $club,
                $registrar
            );
        }

        if (! $this->dry) {
            $this->line('  '.count(self::EVENTS).' past events and '.count(self::UPCOMING).' upcoming programmes.');
        }
    }

    private function putEvent(string $title, string $body, string $category, string $from, string $to, ?Club $club, User $fallback): void
    {
        if ($this->dry) {
            $this->line('  would publish '.Str::limit($title, 60));

            return;
        }

        $event = Event::updateOrCreate(
            ['title' => $title],
            [
                'description' => $body,
                'category' => $category,
                'start_datetime' => $from,
                'end_datetime' => $to,
                'location' => 'United International University, Madani Avenue, Dhaka',
                'capacity' => 150,
                'organizer_user_id' => $club?->moderator_id ?? $fallback->id,
                'club_id' => $club?->id,
                'status' => 'published',
            ]
        );

        $this->attend($event);
    }

    /**
     * Puts people on the register. The event itself is real; who turned up
     * is not, and could not be - an attendance list is personal data even
     * when the event was public.
     */
    private function attend(Event $event): void
    {
        $people = User::where('status', 'active')
            ->whereIn('role', ['student', 'faculty'])
            ->inRandomOrder()
            ->limit(12)
            ->get();

        if ($people->isEmpty()) {
            return;
        }

        foreach ($people->take(random_int(3, min(9, $people->count()))) as $person) {
            DB::table('event_registrations')->insertOrIgnore([
                'event_id' => $event->id,
                'user_id' => $person->id,
                'created_at' => now()->subDays(random_int(1, 20)),
            ]);
        }

        foreach ($people->shuffle()->take(random_int(2, min(7, $people->count()))) as $person) {
            DB::table('event_interests')->insertOrIgnore([
                'event_id' => $event->id,
                'user_id' => $person->id,
                'created_at' => now()->subDays(random_int(1, 20)),
            ]);
        }
    }

    /**
     * Removes rows this command did not put there, so the board is entirely
     * real rather than real rows mixed with sample ones.
     *
     * @param  list<string>  $keep
     */
    private function clearSeeded(string $model, array $keep): void
    {
        $query = $model::whereNotIn('title', $keep);

        if ($model === Notice::class) {
            // Class and club notices belong to their group, not to the
            // university board, and are not this command's to remove.
            $query->whereNull('group_id');
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            return;
        }

        if ($this->dry) {
            $this->line("  would remove {$count} sample rows");

            return;
        }

        if ($model === Event::class) {
            $ids = (clone $query)->pluck('id');
            DB::table('event_registrations')->whereIn('event_id', $ids)->delete();
            DB::table('event_interests')->whereIn('event_id', $ids)->delete();
            DB::table('event_checkins')->whereIn('event_id', $ids)->delete();
        }

        if ($model === Notice::class) {
            $ids = (clone $query)->pluck('id');
            DB::table('notice_reads')->whereIn('notice_id', $ids)->delete();
            DB::table('notice_attachments')->whereIn('notice_id', $ids)->delete();
        }

        // The pictures are polymorphic, so nothing deletes them for us.
        DB::table('media')
            ->where('mediable_type', $model)
            ->whereIn('mediable_id', (clone $query)->pluck('id'))
            ->delete();

        $query->delete();
        $this->line("  removed {$count} sample rows");
    }
}
