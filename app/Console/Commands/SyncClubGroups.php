<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Support\GroupSync;
use Illuminate\Console\Command;

/**
 * Gives every active club its group, and puts the right people in it.
 *
 * Club membership already lives in club_members; this only mirrors it, so the
 * club page stays the place people join and the group follows along.
 */
class SyncClubGroups extends Command
{
    protected $signature = 'uiu:sync-club-groups';

    protected $description = 'Create or refresh the group behind each active club';

    public function handle(): int
    {
        $clubs = Club::where('status', 'active')->get();

        if ($clubs->isEmpty()) {
            $this->warn('No active clubs.');

            return self::SUCCESS;
        }

        $made = 0;
        $skipped = [];

        foreach ($clubs as $club) {
            $group = GroupSync::club($club);

            if (! $group->exists) {
                $skipped[] = $club->name;

                continue;
            }

            $made++;
        }

        $this->info("{$made} club group(s) in step.");

        if ($skipped !== []) {
            $this->warn(count($skipped).' club(s) have no moderator, so no group was made:');
            foreach ($skipped as $name) {
                $this->line('  '.$name);
            }
        }

        return self::SUCCESS;
    }
}
