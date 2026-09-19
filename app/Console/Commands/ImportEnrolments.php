<?php

namespace App\Console\Commands;

use App\Models\CourseEnrollment;
use App\Models\CourseSection;
use App\Models\Trimester;
use App\Models\User;
use App\Support\GroupSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Loads the registrar's class lists and builds the class groups from them.
 *
 * Expected columns:
 *   trimester, course_code, course_title, section, identifier, role
 *
 * `identifier` matches a student ID, an employee ID or an email address, so
 * one file can carry both the class and the person teaching it. `role` is
 * student, teacher or assistant, and defaults to student.
 *
 * The import is a reconcile, not an append. A person in the file is enrolled;
 * a person already enrolled who is absent from the file for a section named in
 * the file is marked dropped and loses the group. Running it twice changes
 * nothing the second time, which is what makes it safe to schedule - and what
 * lets a university API replace it later without any other change.
 */
class ImportEnrolments extends Command
{
    protected $signature = 'uiu:import-enrolments
        {csv : Path to the class list CSV}
        {--dry-run : Report what would happen without writing anything}';

    protected $description = 'Create course sections and enrolments from a CSV, and sync the class groups';

    private const REQUIRED = ['trimester', 'course_code', 'section', 'identifier'];

    private const ROLES = [CourseEnrollment::STUDENT, CourseEnrollment::TEACHER, CourseEnrollment::ASSISTANT];

    public function handle(): int
    {
        $path = (string) $this->argument('csv');

        if (! File::exists($path)) {
            $this->error("No file at {$path}");

            return self::FAILURE;
        }

        $rows = $this->read($path);

        if ($rows === []) {
            $this->error('The CSV has no data rows.');

            return self::FAILURE;
        }

        $missing = array_diff(self::REQUIRED, array_keys($rows[0]));

        if ($missing !== []) {
            $this->error('Missing columns: '.implode(', ', $missing));
            $this->line('Expected: trimester, course_code, course_title, section, identifier, role');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $problems = [];
        $plan = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;

            $trimester = Trimester::where('code', trim((string) $row['trimester']))->first();

            if (! $trimester) {
                $problems[] = "line {$line}: no trimester with code '".trim((string) $row['trimester'])."'";

                continue;
            }

            $identifier = trim((string) $row['identifier']);
            $user = $this->findUser($identifier);

            if (! $user) {
                $problems[] = "line {$line}: no account matches '{$identifier}'";

                continue;
            }

            $role = strtolower(trim((string) ($row['role'] ?? '')));
            $role = in_array($role, self::ROLES, true) ? $role : CourseEnrollment::STUDENT;

            $key = $trimester->id.'|'.trim((string) $row['course_code']).'|'.trim((string) $row['section']);

            $plan[$key] ??= [
                'trimester' => $trimester,
                'course_code' => trim((string) $row['course_code']),
                'course_title' => trim((string) ($row['course_title'] ?? '')) ?: trim((string) $row['course_code']),
                'section' => trim((string) $row['section']),
                'schedule' => trim((string) ($row['schedule'] ?? '')) ?: null,
                'room' => trim((string) ($row['room'] ?? '')) ?: null,
                'people' => [],
            ];

            $plan[$key]['people'][$user->id] = $role;
        }

        if ($problems !== []) {
            $this->warn(count($problems).' row(s) could not be matched:');
            foreach (array_slice($problems, 0, 25) as $problem) {
                $this->line('  '.$problem);
            }
            if (count($problems) > 25) {
                $this->line('  ... and '.(count($problems) - 25).' more');
            }
            $this->newLine();
        }

        if ($plan === []) {
            $this->error('Nothing usable in the file.');

            return self::FAILURE;
        }

        $this->table(
            ['Trimester', 'Section', 'Title', 'People'],
            collect($plan)->map(fn ($s) => [
                $s['trimester']->code,
                $s['course_code'].' '.$s['section'],
                $s['course_title'],
                count($s['people']),
            ])->all()
        );

        if ($dry) {
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $sections = 0;
        $enrolled = 0;
        $dropped = 0;

        DB::transaction(function () use ($plan, &$sections, &$enrolled, &$dropped) {
            foreach ($plan as $entry) {
                $section = CourseSection::updateOrCreate(
                    [
                        'trimester_id' => $entry['trimester']->id,
                        'course_code' => $entry['course_code'],
                        'section_name' => $entry['section'],
                    ],
                    [
                        'course_title' => $entry['course_title'],
                        'schedule_pattern' => $entry['schedule'],
                        'room' => $entry['room'],
                        'source' => 'csv',
                    ]
                );

                $sections++;

                foreach ($entry['people'] as $userId => $role) {
                    CourseEnrollment::updateOrCreate(
                        ['section_id' => $section->id, 'user_id' => $userId],
                        [
                            'role' => $role,
                            'status' => CourseEnrollment::ACTIVE,
                            'source' => 'csv',
                            'dropped_at' => null,
                        ]
                    );

                    $enrolled++;
                }

                // Anybody previously enrolled in this section but absent from
                // the file has left it. The row is kept, marked dropped, so a
                // later import does not quietly put them back.
                $dropped += CourseEnrollment::where('section_id', $section->id)
                    ->whereNotIn('user_id', array_keys($entry['people']))
                    ->where('status', CourseEnrollment::ACTIVE)
                    ->update(['status' => CourseEnrollment::DROPPED, 'dropped_at' => now()]);

                GroupSync::section($section->fresh());
            }
        });

        $this->newLine();
        $this->info("{$sections} section(s), {$enrolled} enrolment(s) written.");

        if ($dropped > 0) {
            $this->warn("{$dropped} enrolment(s) marked dropped because the file no longer lists them.");
        }

        $this->line('Class groups are in step with the roster.');

        return self::SUCCESS;
    }

    /** Student ID, employee ID or email - whichever the registrar exported. */
    private function findUser(string $identifier): ?User
    {
        if ($identifier === '') {
            return null;
        }

        return User::where('student_id', $identifier)
            ->orWhere('employee_id', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }

    /** @return list<array<string, string>> */
    private function read(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $header = null;
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $line[0] = preg_replace('/^\x{FEFF}/u', '', (string) $line[0]);
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $line);

                continue;
            }

            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $line = array_slice($line, 0, count($header));
            $rows[] = array_combine($header, array_pad($line, count($header), ''));
        }

        fclose($handle);

        return $rows;
    }
}
