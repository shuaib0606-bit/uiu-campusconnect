<?php

namespace App\Console\Commands;

use App\Models\Trimester;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Defines the teaching calendar. Everything a class group does about time -
 * the end-of-trimester countdown and the archive twenty days later - is read
 * from these two dates, so they have to be right.
 */
class ManageTrimester extends Command
{
    protected $signature = 'uiu:trimester
        {name? : Display name, e.g. "Fall 2026"}
        {--code= : Short unique code, e.g. 2026-3}
        {--start= : First teaching day, YYYY-MM-DD}
        {--end= : Last teaching day, YYYY-MM-DD}
        {--list : Show the trimesters already defined}';

    protected $description = 'Create or update a trimester';

    public function handle(): int
    {
        if ($this->option('list') || ! $this->argument('name')) {
            return $this->show();
        }

        $code = (string) $this->option('code');
        $start = (string) $this->option('start');
        $end = (string) $this->option('end');

        if ($code === '' || $start === '' || $end === '') {
            $this->error('--code, --start and --end are all required when creating a trimester.');

            return self::FAILURE;
        }

        try {
            $startsOn = Carbon::parse($start)->startOfDay();
            $endsOn = Carbon::parse($end)->startOfDay();
        } catch (\Throwable) {
            $this->error('Dates must look like 2026-09-01.');

            return self::FAILURE;
        }

        if ($endsOn->lessThanOrEqualTo($startsOn)) {
            $this->error('The end date has to be after the start date.');

            return self::FAILURE;
        }

        $trimester = Trimester::updateOrCreate(
            ['code' => $code],
            [
                'name' => (string) $this->argument('name'),
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]
        );

        $this->info(($trimester->wasRecentlyCreated ? 'Created ' : 'Updated ').$trimester->name.' ('.$trimester->code.')');
        $this->line('Teaching: '.$trimester->starts_on->format('M j, Y').' to '.$trimester->ends_on->format('M j, Y'));
        $this->line('Class groups archive on '.$trimester->archivesOn()->format('M j, Y')
            .' ('.Trimester::GRACE_DAYS.' days after teaching ends).');

        return self::SUCCESS;
    }

    private function show(): int
    {
        $rows = Trimester::orderBy('starts_on')->get();

        if ($rows->isEmpty()) {
            $this->warn('No trimesters defined yet.');
            $this->line('Example: php artisan uiu:trimester "Fall 2026" --code=2026-3 --start=2026-09-01 --end=2026-12-20');

            return self::SUCCESS;
        }

        $current = Trimester::current();

        $this->table(
            ['Code', 'Name', 'Teaching', 'Archives on', 'Now'],
            $rows->map(fn (Trimester $t) => [
                $t->code,
                $t->name,
                $t->starts_on->format('M j, Y').' - '.$t->ends_on->format('M j, Y'),
                $t->archivesOn()->format('M j, Y'),
                $current && $current->id === $t->id ? 'current' : '',
            ])->all()
        );

        return self::SUCCESS;
    }
}
