<?php

namespace App\Console\Commands;

use App\Models\CommunityGroup;
use App\Models\Trimester;
use Illuminate\Console\Command;

/**
 * Closes class groups once a trimester has been over for its grace period.
 *
 * Archiving, not deleting. A student who never got round to downloading the
 * slides still can; nothing anyone wrote is destroyed. The room simply stops
 * accepting anything new, which is what the countdown inside it promises.
 *
 * Meant to run daily. Running it more often changes nothing.
 */
class ArchiveCourseGroups extends Command
{
    protected $signature = 'uiu:archive-course-groups {--dry-run : List what would be archived}';

    protected $description = 'Archive class groups whose trimester ended more than the grace period ago';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->startOfDay();

        $due = Trimester::whereDate('ends_on', '<=', $cutoff->copy()->subDays(Trimester::GRACE_DAYS))->get();

        if ($due->isEmpty()) {
            $this->info('No trimester is past its grace period.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($due as $trimester) {
            $groups = CommunityGroup::where('kind', CommunityGroup::KIND_COURSE)
                ->whereNull('archived_at')
                ->whereIn('source_id', $trimester->sections()->pluck('id'))
                ->get();

            foreach ($groups as $group) {
                $this->line(($dry ? 'would archive  ' : 'archiving      ').$group->name);

                if (! $dry) {
                    $group->forceFill(['archived_at' => now()])->save();
                }

                $total++;
            }
        }

        $this->newLine();

        $this->info($dry
            ? "{$total} class group(s) would be archived."
            : "{$total} class group(s) archived. Their materials stay downloadable.");

        return self::SUCCESS;
    }
}
