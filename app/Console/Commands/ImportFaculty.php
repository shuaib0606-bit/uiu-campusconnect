<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Creates faculty accounts from the university's staff list.
 *
 * Expected columns: full_name, email, department, designation, employee_id,
 * authority_level, avatar_initials, one_time_password, photo_file.
 */
class ImportFaculty extends Command
{
    protected $signature = 'uiu:import-faculty
        {csv : Path to the staff CSV}
        {--photos= : Folder holding the photo files, defaults to the CSV folder}
        {--dry-run : Report what would happen without writing anything}';

    protected $description = 'Create or update faculty accounts from a CSV';

    private const REQUIRED = ['full_name', 'email', 'department', 'designation', 'one_time_password'];

    /** Where photos are kept on the public disk. */
    private const PHOTO_DIR = 'profiles/faculty';

    public function handle(): int
    {
        $csv = $this->argument('csv');

        if (! File::exists($csv)) {
            $this->error("No file at {$csv}");

            return self::FAILURE;
        }

        $photoDir = rtrim($this->option('photos') ?: dirname($csv), '/');
        $dry = (bool) $this->option('dry-run');

        $rows = $this->read($csv);

        if ($rows === []) {
            $this->error('The CSV has no data rows.');

            return self::FAILURE;
        }

        $missing = array_diff(self::REQUIRED, array_keys($rows[0]));

        if ($missing !== []) {
            $this->error('Missing columns: '.implode(', ', $missing));

            return self::FAILURE;
        }

        if (! $dry) {
            File::ensureDirectoryExists(storage_path('app/public/'.self::PHOTO_DIR));
        }

        $created = 0;
        $updated = 0;
        $photos = 0;
        $notes = [];

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $email = $this->firstEmail($row['email']);
            $name = trim($row['full_name']);

            if ($email === '' || $name === '') {
                $notes[] = [$line, $name ?: '(no name)', 'Skipped: missing name or email'];
                $bar->advance();

                continue;
            }

            if (str_contains($row['email'], ',')) {
                $notes[] = [$line, $name, 'Several emails given, used '.$email];
            }

            $attributes = [
                'full_name' => $name,
                'role' => 'faculty',
                'authority_level' => trim($row['authority_level'] ?? '') ?: 'faculty',
                'department' => $this->department($row['department']),
                'designation' => trim($row['designation']),
                'employee_id' => trim($row['employee_id'] ?? '') ?: null,
                'status' => 'active',
                'is_verified' => true,
                'avatar_initials' => trim($row['avatar_initials'] ?? '') ?: $this->initials($name),
            ];

            $user = User::where('email', $email)->first();

            if ($dry) {
                $user ? $updated++ : $created++;
                $bar->advance();

                continue;
            }

            if ($user) {
                // Re-running must not undo a password the person has already set.
                $user->update($attributes);
                $updated++;
            } else {
                $user = User::create($attributes + [
                    'email' => $email,
                    'password' => $row['one_time_password'],
                    'must_change_password' => true,
                    'password_changed_at' => now(),
                ]);
                $created++;
            }

            $path = $this->storePhoto($photoDir, $row['photo_file'] ?? '', $email);

            if ($path !== null) {
                $photos++;
            } elseif (trim($row['photo_file'] ?? '') !== '') {
                $notes[] = [$line, $name, 'Photo not found: '.trim($row['photo_file'])];
            }

            UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                array_filter(['profile_photo_path' => $path], fn ($v) => $v !== null)
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info($dry
            ? "Dry run: {$created} would be created, {$updated} would be updated."
            : "Created {$created}, updated {$updated}, attached {$photos} photos.");

        if ($notes !== []) {
            $this->newLine();
            $this->warn(count($notes).' row(s) need attention:');
            $this->table(['Line', 'Name', 'Note'], $notes);
        }

        if (! $dry) {
            $this->newLine();
            $this->line('Every imported account must change its password at first sign in.');
            $this->line('Run php artisan storage:link once if you have not already, or the photos will not load.');
        }

        return self::SUCCESS;
    }

    /** Reads the CSV into an array of rows keyed by column name. */
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
                $header = array_map('trim', $line);

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

    /** Some rows carry more than one address; the first is the one to use. */
    private function firstEmail(string $value): string
    {
        return Str::lower(trim(Str::before($value, ',')));
    }

    /** The source list writes some departments with a prefix and some without. */
    private function department(string $value): string
    {
        return trim(Str::after(trim($value), 'Department of ') ?: trim($value));
    }

    private function initials(string $name): string
    {
        $initials = collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');

        return $initials ?: 'UI';
    }

    /** Copies one photo onto the public disk and returns its stored path. */
    private function storePhoto(string $sourceDir, string $file, string $email): ?string
    {
        $file = trim($file);

        if ($file === '') {
            return null;
        }

        $source = $sourceDir.'/'.$file;

        if (! File::exists($source)) {
            return null;
        }

        // Named after the source file, not the email: several staff share a
        // local part (abrar@cse and abrar@eee are different people) and would
        // otherwise overwrite each other's portrait.
        $name = Str::slug(pathinfo($file, PATHINFO_FILENAME)).'.'.Str::lower(File::extension($source));
        $relative = self::PHOTO_DIR.'/'.$name;

        File::copy($source, storage_path('app/public/'.$relative), true);

        return $relative;
    }
}
