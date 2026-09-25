<?php

namespace App\Console\Commands;

use App\Models\FacultyProfileSection;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\FacultyProfileText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Brings faculty accounts up to date with the UIU department websites.
 *
 * Input is the export made from those websites: UIU-Faculty-Details.zip
 * (faculty.json inside) and, optionally, UIU-Faculty-Photos.zip. Either
 * can also be given as the folder it unzips to.
 *
 * Why an update and not a fresh import: faculty accounts are what course
 * sections, class groups, clubs, assignments, notices and messages point
 * at. Replacing them would orphan all of that and sign everybody out, so
 * each website entry is matched to the account that already exists and
 * only the facts the website is the source for are refreshed.
 *
 *   Matched by     email first; then by name (titles and punctuation
 *                  ignored) within the same department, and only when
 *                  exactly one account fits.
 *   Refreshed      designation, department, the profile link, and:
 *                  - room and PABX, unless the person changed them here
 *                  - website sections they have not edited or removed
 *                    (see sectionPlan)
 *                  - the photo, only if they never uploaded their own
 *                  A name only gains an earned title ("Dr."); it never
 *                  loses one and never becomes a different name.
 *   Filled once    research interests, while empty, from a short list on
 *                  the website. The bio is never written: it is the
 *                  person's own one or two lines.
 *   Never touched  email, password, privacy switches, account status,
 *                  role, and anything belonging to an administrator.
 *   New people     get an account like the first import did: a one-time
 *                  password they must change at first sign in. The
 *                  passwords go to a CSV outside the project folder.
 *   Missing people accounts that are not on any website are listed, not
 *                  deleted - they may be on leave, adjunct, or gone.
 *
 * Nothing is written without --apply. The dry run prints the same report.
 */
class SyncFacultyFromWebsite extends Command
{
    protected $signature = 'uiu:sync-faculty
        {details : UIU-Faculty-Details.zip, or the folder it unzips to}
        {--photos= : UIU-Faculty-Photos.zip, or its folder}
        {--apply : Write the changes. Without it this is a dry run}
        {--no-create : Only update existing accounts; do not create new ones}
        {--credentials= : CSV for the one-time passwords of new accounts (default: your Desktop)}';

    protected $description = 'Update faculty accounts from the UIU department websites (dry run unless --apply)';

    /** Where imported photos live on the public disk, as ImportFaculty uses. */
    private const PHOTO_DIR = 'profiles/faculty';

    /** Website department names to the names this site already uses. */
    private const DEPARTMENTS = [
        'Department of CSE' => 'Computer Science and Engineering',
        'Department of EEE' => 'Electrical and Electronic Engineering',
        'Department of Civil Engineering' => 'Civil Engineering',
        'Department of English' => 'English',
        'Department of Economics' => 'Economics',
        'Department of Environment & Development Studies' => 'Environment & Development Studies',
        'Department of Media Studies & Journalism' => 'Media Studies & Journalism',
        'Department of Pharmacy' => 'Pharmacy',
        'Department of Biotechnology and Genetic Engineering' => 'Biotechnology and Genetic Engineering',
        'Business Administration' => 'Business Administration',
        'Institute of Natural Sciences' => 'Institute of Natural Sciences',
    ];

    private ?\ZipArchive $photoZip = null;

    private ?string $photoDir = null;

    /** Replaced import photos, removed only once the database work has committed. */
    private array $obsolete = [];

    /** @var list<array{string, string, string, string}> action, name, account, detail */
    private array $report = [];

    public function handle(): int
    {
        // Some website originals are 24-megapixel photos. Decoding one to
        // shrink it takes well over PHP's usual 128 MB, and a PHP set to hide
        // errors (as XAMPP's is) would then stop without a word. So the limit
        // is raised for this command, and a fatal error is always reported.
        if ($this->bytes((string) ini_get('memory_limit')) < 1024 * 1024 * 1024) {
            @ini_set('memory_limit', '1024M');
        }

        register_shutdown_function(function () {
            $e = error_get_last();

            if ($e && in_array($e['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                fwrite(STDERR, PHP_EOL.'Stopped: '.$e['message'].PHP_EOL.'Nothing was saved - the database changes are made in one transaction.'.PHP_EOL);
            }
        });

        $apply = (bool) $this->option('apply');
        $records = $this->records($this->expand((string) $this->argument('details')));

        if ($records === null) {
            return self::FAILURE;
        }

        if (! $this->openPhotos()) {
            return self::FAILURE;
        }

        $this->info(($apply ? 'Applying' : 'Dry run').': '.count($records).' people from the UIU websites.');
        $this->newLine();

        $accounts = User::with('profile')->get();
        $byEmail = $accounts->keyBy(fn (User $u) => Str::lower($u->email));
        $counts = ['updated' => 0, 'unchanged' => 0, 'created' => 0, 'skipped' => 0, 'photos' => 0, 'by_name' => 0];
        $credentials = [];
        $seen = [];

        $work = function () use ($records, $accounts, $byEmail, $apply, &$counts, &$credentials, &$seen) {
            foreach ($records as $record) {
                // Some pages list two addresses ("a@uiu.ac.bd, b@gmail.com").
                // Any of them can identify the account; the first is the one
                // a new account is created with.
                $emails = $this->emails((string) ($record['email'] ?? ''));
                $email = $emails[0] ?? '';
                $department = self::DEPARTMENTS[$record['department'] ?? ''] ?? Str::after((string) ($record['department'] ?? ''), 'Department of ');
                $user = null;

                foreach ($emails as $candidate) {
                    $user ??= $byEmail->get($candidate);
                }

                $how = 'email';

                if (! $user) {
                    $user = $this->byName($accounts, (string) $record['name'], $department);
                    $how = 'name';
                }

                if ($user && $user->role === 'admin') {
                    $this->note('skipped', $record['name'], $user->email, 'administrator account - left alone');
                    $counts['skipped']++;

                    continue;
                }

                if ($user && ! $user->exists) {
                    $this->note('skipped', $record['name'], $email, 'same address as another new entry ('.$user->full_name.') - one account only');
                    $counts['skipped']++;

                    continue;
                }

                if ($user && isset($seen[$user->id])) {
                    $this->note('skipped', $record['name'], $user->email, 'same account already matched to '.$seen[$user->id]);
                    $counts['skipped']++;

                    continue;
                }

                if ($user) {
                    $seen[$user->id] = $record['name'];

                    if ($how === 'name') {
                        $counts['by_name']++;
                        $this->note('matched by name', $record['name'], $user->email,
                            'website email is '.($email ?: 'missing').' - the account email was not changed');
                    }

                    $changes = $this->update($user, $record, $department, $apply);
                    $changes === [] ? $counts['unchanged']++ : $counts['updated']++;

                    if (in_array('photo', $changes, true)) {
                        $counts['photos']++;
                    }

                    if ($changes !== []) {
                        $this->note('update', $record['name'], $user->email, implode(', ', $changes));
                    }

                    continue;
                }

                if ($this->option('no-create')) {
                    $this->note('not created', $record['name'], $email, '--no-create');
                    $counts['skipped']++;

                    continue;
                }

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->note('skipped', $record['name'], $email ?: '-', 'no usable email on the website');
                    $counts['skipped']++;

                    continue;
                }

                $password = $this->oneTimePassword();

                if ($apply) {
                    $user = User::create([
                        'full_name' => $this->withoutCourtesy($record['name']),
                        'email' => $email,
                        'password' => $password,
                        'role' => 'faculty',
                        'authority_level' => 'faculty',
                        'department' => $department,
                        'designation' => $this->designation((string) ($record['designation'] ?? '')),
                        'status' => 'active',
                        'is_verified' => true,
                        'must_change_password' => true,
                        'password_changed_at' => now(),
                        'avatar_initials' => $this->initials($record['name']),
                    ]);
                    $user->setRelation('profile', null);
                    $changes = $this->update($user, $record, $department, true);

                    if (in_array('photo', $changes, true)) {
                        $counts['photos']++;
                    }

                    $credentials[] = [$record['name'], $email, $department, $password];
                    $seen[$user->id] = $record['name'];
                } elseif (! empty($record['photo_file']) && ($this->photoZip || $this->photoDir)) {
                    $counts['photos']++;
                }

                // A later entry with the same address (one person listed under
                // two departments) must find this account, not create another.
                $placeholder = $user ?? new User(['full_name' => $record['name'], 'email' => $email]);
                $byEmail->put($email, $placeholder);
                $accounts->push($placeholder);

                $counts['created']++;
                $this->note('create', $record['name'], $email, $department);
            }
        };

        $apply ? DB::transaction($work) : $work();

        foreach ($this->obsolete as $old) {
            Storage::disk('public')->delete($old);
        }

        $missing = $this->missing($accounts, $seen);

        $this->summary($counts, count($missing), $apply);
        $this->writeReport($apply);

        if ($credentials !== []) {
            $this->writeCredentials($credentials);
        }

        if (! $apply) {
            $this->newLine();
            $this->line('Nothing was written. Back up the database, then run the same command with --apply.');
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // One account
    // ------------------------------------------------------------------

    /** @return list<string> what changed, for the report */
    private function update(User $user, array $record, string $department, bool $apply): array
    {
        $changes = [];
        $userData = [];

        $designation = $this->designation((string) ($record['designation'] ?? ''));

        if ($designation !== '' && $designation !== (string) $user->designation) {
            $userData['designation'] = $designation;
            $changes[] = 'designation: "'.($user->designation ?: '-').'" to "'.$designation.'"';
        }

        if ($department !== '' && $department !== (string) $user->department) {
            $userData['department'] = $department;
            $changes[] = 'department: "'.($user->department ?: '-').'" to "'.$department.'"';
        }

        // Only an earned title is taken from the website ("Rahman" becomes
        // "Dr. Rahman"). Mr./Ms. are not added - names here are written
        // without them - a title is never removed, and a different name is
        // never taken over.
        $siteName = $this->withoutCourtesy($record['name']);

        if ($siteName !== $user->full_name
            && $this->core($siteName) === $this->core($user->full_name)
            && $this->hasTitle($siteName) && ! $this->hasTitle($user->full_name)) {
            $userData['full_name'] = $siteName;
            $changes[] = 'name: "'.$user->full_name.'" to "'.$siteName.'"';
        }

        $profile = $user->profile;
        $profileData = [];
        $sections = $record['sections'] ?? [];

        // Room and PABX follow the website only while nobody has changed them
        // here: uiu_room / uiu_pabx hold what the website said last time, and
        // a value that no longer matches it was typed in by the person.
        foreach (['room' => 'uiu_room', 'pabx' => 'uiu_pabx'] as $field => $last) {
            $value = trim((string) ($record[$field] ?? ''));
            $current = (string) ($profile?->{$field} ?? '');
            // Cleared on purpose counts as a change too.
            $ownEdit = $current !== (string) ($profile?->{$last} ?? '');

            if ($value !== '' && $value !== $current && ! $ownEdit) {
                $profileData[$field] = $value;
                $changes[] = $field.': "'.($current ?: '-').'" to "'.$value.'"';
            }

            if ($value !== (string) ($profile?->{$last} ?? '')) {
                $profileData[$last] = $value ?: null;
            }
        }

        if (($record['profile_url'] ?? '') !== ($profile?->uiu_profile_url ?? '')) {
            $profileData['uiu_profile_url'] = $record['profile_url'] ?? null;
        }

        // The bio is left to the person: a line or two they write, not a page
        // from the website. Research interests are filled only while empty,
        // and only from a website section that really is a short list.
        if (! $profile?->research_interests && ($research = FacultyProfileText::researchTags($sections))) {
            $profileData['research_interests'] = $research;
            $changes[] = 'research interests filled';
        }

        $plan = $this->sectionPlan($user, $sections);

        if ($plan['summary'] !== []) {
            $changes[] = 'sections: '.implode(', ', $plan['summary']);
        }

        $photo = $this->photoFor($record, $profile);

        if ($photo !== null) {
            $changes[] = 'photo';
        }

        $pending = $changes !== [] || $profileData !== [] || $plan['update'] !== [] || $plan['create'] !== [] || $plan['delete'] !== [];

        if (! $apply || ! $pending) {
            return $changes;
        }

        if ($userData !== []) {
            $user->forceFill($userData)->save();
        }

        if ($photo !== null) {
            $old = $profile?->profile_photo_path;
            $profileData['profile_photo_path'] = $this->storePhoto($photo, $user->email);

            if ($old && $old !== $profileData['profile_photo_path']) {
                $this->obsolete[] = $old;
            }
        }

        $profileData['uiu_synced_at'] = now();
        UserProfile::updateOrCreate(['user_id' => $user->id], $profileData);

        $this->applySections($user, $plan);

        return $changes;
    }

    /**
     * What the website's sections mean for this person's existing ones.
     *
     * Sections are matched by the heading they came from (source_key).
     *   - untouched website section: follows the website (new text, and it
     *     disappears if the website drops that heading)
     *   - edited here: kept as the person wrote it; only website_body is
     *     refreshed, for "restore website version"
     *   - removed here: stays removed - the website does not bring it back
     *   - added here (custom): never touched
     *   - a heading new on the website: added at the end
     * Order and visibility are the person's and are never changed.
     *
     * @param  array<string, string>  $sections  website title => markdown
     */
    private function sectionPlan(User $user, array $sections): array
    {
        $existing = $user->exists
            ? FacultyProfileSection::where('user_id', $user->id)->where('source', FacultyProfileSection::WEBSITE)->get()->keyBy('source_key')
            : collect();

        $plan = ['update' => [], 'create' => [], 'delete' => [], 'summary' => []];
        $seen = [];

        foreach ($sections as $title => $body) {
            $title = Str::limit(trim((string) $title), 150, '');
            $key = mb_strtolower($title);
            $body = (string) $body;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $row = $existing->get($key);

            if (! $row) {
                $plan['create'][] = [$title, $body];

                continue;
            }

            $untouched = $row->edited_at === null && $row->removed_at === null;
            $fields = [];

            if ($row->website_body !== $body) {
                $fields['website_body'] = $body;
            }

            if ($untouched && ($row->body !== $body || $row->title !== $title)) {
                $fields['body'] = $body;
                $fields['title'] = $title;
            }

            if ($fields !== []) {
                $plan['update'][$row->id] = $fields;

                if (isset($fields['body'])) {
                    $plan['summary'][] = 'updated '.$title;
                }
            }
        }

        foreach ($existing as $key => $row) {
            if (! isset($seen[$key]) && $row->edited_at === null && $row->removed_at === null) {
                $plan['delete'][] = $row->id;
                $plan['summary'][] = 'dropped '.$row->title;
            }
        }

        foreach ($plan['create'] as [$title]) {
            $plan['summary'][] = 'added '.$title;
        }

        return $plan;
    }

    private function applySections(User $user, array $plan): void
    {
        foreach ($plan['update'] as $id => $fields) {
            FacultyProfileSection::whereKey($id)->update($fields);
        }

        if ($plan['delete'] !== []) {
            FacultyProfileSection::whereIn('id', $plan['delete'])->delete();
        }

        $position = (int) FacultyProfileSection::where('user_id', $user->id)->max('position');
        $first = ! FacultyProfileSection::where('user_id', $user->id)->exists();

        foreach ($plan['create'] as $i => [$title, $body]) {
            FacultyProfileSection::create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'website_body' => $body,
                'source' => FacultyProfileSection::WEBSITE,
                'source_key' => mb_strtolower($title),
                'position' => $first ? $i : ++$position,
            ]);
        }
    }

    /**
     * The website photo's bytes, when it should replace what the account
     * shows: only if there is no photo yet, or the current one came from an
     * import (profiles/faculty/...). A photo the person uploaded is theirs.
     */
    private function photoFor(array $record, ?UserProfile $profile): ?string
    {
        $file = $record['photo_file'] ?? null;

        if (! $file || (! $this->photoZip && ! $this->photoDir)) {
            return null;
        }

        $current = $profile?->profile_photo_path;

        if ($current && ! str_starts_with($current, self::PHOTO_DIR.'/')) {
            return null;
        }

        $bytes = $this->photoZip
            ? $this->photoZip->getFromName($file)
            : (File::exists($this->photoDir.'/'.$file) ? File::get($this->photoDir.'/'.$file) : false);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        $bytes = $this->shrink($bytes);

        // Same picture already in place: nothing to do.
        if ($current && Storage::disk('public')->exists($current) && md5(Storage::disk('public')->get($current)) === md5($bytes)) {
            return null;
        }

        return $bytes;
    }

    private function storePhoto(string $bytes, string $email): string
    {
        // Named after the whole address: abrar@cse and abrar@eee are two people.
        $path = self::PHOTO_DIR.'/'.Str::slug(str_replace('@', ' at ', $email)).'.jpg';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /**
     * Website originals run to several megabytes. A profile picture is never
     * shown larger than a few hundred pixels, so it is stored at most 800px
     * on its long side, as JPEG. Without GD the original is kept.
     */
    private function shrink(string $bytes): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $bytes;
        }

        $size = @getimagesizefromstring($bytes);

        // Too large to decode safely even with the raised limit: keep as is.
        if (! $size || $size[0] * $size[1] > 60_000_000) {
            return $bytes;
        }

        try {
            $image = @imagecreatefromstring($bytes);
        } catch (\Throwable) {
            return $bytes;
        }

        if ($image === false) {
            return $bytes;
        }

        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, 800 / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($nw, $nh);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($canvas, null, 85);
        $out = (string) ob_get_clean();

        imagedestroy($image);
        imagedestroy($canvas);

        return $out !== '' ? $out : $bytes;
    }

    // ------------------------------------------------------------------
    // Matching
    // ------------------------------------------------------------------

    private function byName($accounts, string $name, string $department): ?User
    {
        $same = $accounts->filter(fn (User $u) => in_array($u->role, ['faculty', 'official'], true)
            && $this->sameName($u->full_name, $name));

        $inDept = $same->filter(fn (User $u) => Str::lower((string) $u->department) === Str::lower($department));

        if ($inDept->count() === 1) {
            return $inDept->first();
        }

        return $same->count() === 1 ? $same->first() : null;
    }

    /**
     * Two spellings of one name: equal once titles and punctuation go, or
     * word for word where one side only has the initial ("Mohammad A.
     * Ashraf" and "Mohammad Ali Ashraf").
     */
    private function sameName(?string $a, ?string $b): bool
    {
        $x = explode(' ', $this->core($a));
        $y = explode(' ', $this->core($b));

        if ($x === [''] || count($x) !== count($y)) {
            return false;
        }

        foreach ($x as $i => $word) {
            $other = $y[$i];

            if ($word === $other) {
                continue;
            }

            $short = strlen($word) === 1 ? $word : (strlen($other) === 1 ? $other : null);
            $long = $short === $word ? $other : $word;

            if ($short === null || ! str_starts_with($long, $short)) {
                return false;
            }
        }

        return true;
    }

    /** "Mr. Rakib Ahsan" -> "Rakib Ahsan". Academic titles stay. */
    private function withoutCourtesy(string $name): string
    {
        return trim(preg_replace('/^(mr|mrs|ms|miss)\.?\s+/i', '', trim($name)));
    }

    private function hasTitle(?string $name): bool
    {
        return (bool) preg_match('/^(dr|prof|professor|engr)\b\.?/i', trim((string) $name));
    }

    /** @return list<string> every valid address in the field, lower case */
    private function emails(string $value): array
    {
        return array_values(array_filter(
            array_map(fn ($e) => Str::lower(trim($e)), preg_split('/[\s,;\/]+/', $value) ?: []),
            fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false
        ));
    }

    /** A name without titles, dots or case: "Dr. Md. Abul Kashem Mia" = "md abul kashem mia". */
    private function core(?string $name): string
    {
        $name = Str::lower((string) $name);
        $name = preg_replace('/\b(dr|prof|professor|mr|mrs|ms|miss|engr|sir)\b\.?/u', ' ', $name);
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * "Professor, Dept. of CSE & Dean, SoSE" -> "Professor & Dean, SoSE".
     * The department is its own field here, so it is not repeated in the title.
     */
    private function designation(string $value): string
    {
        // Department names that contain "&" would otherwise be cut at the
        // ampersand ("Lecturer & Genetic Engineering"), so they are spelled
        // with "and" before anything is removed.
        $value = preg_replace('/\b(Biotechnology|Media Studies|Environment|Business|Electrical|Computer Science)\s*&\s*(Genetic Engineering|Journalism|Development Studies|Economics|Electronic Engineering|Engineering)\b/iu', '$1 and $2', $value);
        $value = preg_replace('/,\s*School of Business\s*(&|and)\s*Economics(\s*\(SoBE\))?/iu', '', $value);
        $value = preg_replace('/,\s*SoBE(?=\s*(&|,|$))/u', '', $value);
        $value = preg_replace('/(,|-)\s*(Dept\.?|Department|School|Institute)\s+of\s+[^,&]+?(\s*\([^)]*\))?(?=\s*(&|,|$))/iu', '', $value);

        // "Lecturer-On Leave", "Lecturer- On Leave", "Lecturer - On Study Leave"
        // all read the same way: "Lecturer (On Leave)".
        $value = preg_replace('/\s*-\s*(On\s+(Study\s+)?Leave)\b/i', ' ($1)', (string) $value);

        return trim(preg_replace('/\s+/', ' ', (string) $value), " ,");
    }

    /** Accounts that look like faculty but were not on any website. */
    private function missing($accounts, array $seen): array
    {
        $missing = $accounts->filter(fn (User $u) => $u->role === 'faculty'
            && $u->status === 'active'
            && ! isset($seen[$u->id])
            && ! str_ends_with(Str::lower($u->email), '@demo.uiu.test'));

        foreach ($missing as $u) {
            $this->note('not on website', $u->full_name, $u->email, 'left as it is - check whether they still teach at UIU');
        }

        return $missing->all();
    }

    // ------------------------------------------------------------------
    // Input
    // ------------------------------------------------------------------

    private function records(string $path): ?array
    {
        $json = null;

        if (is_dir($path)) {
            $file = rtrim($path, '/').'/faculty.json';
            $json = File::exists($file) ? File::get($file) : null;
        } elseif (File::exists($path)) {
            if (! class_exists(\ZipArchive::class)) {
                $this->error('This PHP has no zip support. Unzip the file and pass the folder instead.');

                return null;
            }

            $zip = new \ZipArchive;

            if ($zip->open($path) === true) {
                $json = $zip->getFromName('faculty.json') ?: null;
                $zip->close();
            }
        }

        $records = $json ? json_decode($json, true) : null;

        if (! is_array($records) || $records === []) {
            $this->error("No faculty.json found in {$path}");

            return null;
        }

        return array_values(array_filter($records, fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== ''));
    }

    /**
     * "--photos=~/Desktop/x.zip" reaches PHP with the ~ still in it: the
     * shell only expands a tilde at the start of a word, and after "=" the
     * whole thing is one word. So it is expanded here.
     */
    private function expand(string $path): string
    {
        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? '');

        return $home !== '' && preg_match('#^~(/|$)#', $path) ? $home.substr($path, 1) : $path;
    }

    private function openPhotos(): bool
    {
        $path = $this->option('photos') ? $this->expand((string) $this->option('photos')) : null;

        if (! $path) {
            $this->warn('No --photos given: photos will not be updated.');

            return true;
        }

        if (is_dir($path)) {
            $this->photoDir = rtrim($path, '/');

            return true;
        }

        if (! File::exists($path) || ! class_exists(\ZipArchive::class)) {
            $this->error("Cannot read photos from {$path} (unzip it and pass the folder if this PHP has no zip support).");

            return false;
        }

        $this->photoZip = new \ZipArchive;

        if ($this->photoZip->open($path) !== true) {
            $this->error("Cannot open {$path}");

            return false;
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Output
    // ------------------------------------------------------------------

    private function note(string $action, string $name, string $account, string $detail): void
    {
        $this->report[] = [$action, $name, $account, $detail];
    }

    private function summary(array $counts, int $missing, bool $apply): void
    {
        $verb = $apply ? '' : 'would be ';

        $this->table(['', 'People'], [
            ['Accounts '.$verb.'updated', $counts['updated']],
            ['  of which matched by name (email differs)', $counts['by_name']],
            ['  photos '.$verb.'replaced', $counts['photos']],
            ['Already up to date', $counts['unchanged']],
            ['New accounts '.$verb.'created', $counts['created']],
            ['Skipped', $counts['skipped']],
            ['Faculty accounts not on any website (not changed)', $missing],
        ]);

        $attention = array_filter($this->report, fn ($r) => in_array($r[0], ['matched by name', 'skipped', 'not on website'], true));

        if ($attention !== []) {
            $this->newLine();
            $this->warn('Worth a look:');
            $this->table(['What', 'Name', 'Account', 'Detail'], array_map(fn ($r) => [$r[0], $r[1], $r[2], Str::limit($r[3], 70)], $attention));
        }
    }

    private function writeReport(bool $apply): void
    {
        $dir = storage_path('app/faculty-sync');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/'.($apply ? 'applied' : 'dry-run').'-'.now()->format('Y-m-d-His').'.csv';

        $handle = fopen($file, 'w');
        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, ['action', 'name', 'account', 'detail']);

        foreach ($this->report as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        $this->newLine();
        $this->line('Full list of every change: '.$file);
    }

    private function writeCredentials(array $rows): void
    {
        $file = $this->option('credentials');

        if (! $file) {
            $home = getenv('HOME') ?: '';
            $desktop = $home !== '' && is_dir($home.'/Desktop') ? $home.'/Desktop' : storage_path('app');
            $file = $desktop.'/uiu-new-faculty-passwords-'.now()->format('Y-m-d-His').'.csv';
        }

        $handle = fopen($file, 'w');
        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, ['name', 'email', 'department', 'one_time_password']);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        @chmod($file, 0600);

        $this->newLine();
        $this->warn(count($rows).' new account(s). Their one-time passwords are in:');
        $this->line('  '.$file);
        $this->line('Hand them out privately, then delete that file. Each must change it at first sign in.');
    }

    private function oneTimePassword(): string
    {
        // Letters and digits without look-alikes (0/O, 1/l), easy to read out.
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $out = '';

        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    /** "128M" -> bytes; -1 (no limit) counts as unlimited. */
    private function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }

        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }

    private function initials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', $this->core($name))));

        return Str::upper(implode('', array_map(fn ($w) => Str::substr($w, 0, 1), array_slice($words, 0, 2)))) ?: 'UI';
    }
}
