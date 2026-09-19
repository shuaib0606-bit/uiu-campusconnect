<?php

namespace App\Console\Commands;

use App\Http\Controllers\GroupController;
use App\Models\CommunityGroupFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Group files used to be written to the public disk, where anybody holding the
 * address could download them without signing in. New uploads go to the
 * private disk; this moves the ones already on disk so the old addresses stop
 * working. Safe to run more than once.
 */
class SecureGroupFiles extends Command
{
    protected $signature = 'uiu:secure-group-files {--dry-run : List what would move without moving it}';

    protected $description = 'Move group files off the publicly readable disk';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk(GroupController::FILE_DISK);
        $dry = (bool) $this->option('dry-run');

        $moved = 0;
        $already = 0;
        $missing = [];

        foreach (CommunityGroupFile::all() as $file) {
            if ($private->exists($file->file_path)) {
                $already++;

                // A copy left behind on the public disk is still readable.
                if ($public->exists($file->file_path) && ! $dry) {
                    $public->delete($file->file_path);
                }

                continue;
            }

            if (! $public->exists($file->file_path)) {
                $missing[] = $file->file_path;

                continue;
            }

            $this->line(($dry ? 'would move  ' : 'moving      ').$file->file_path);

            if (! $dry) {
                $private->put($file->file_path, $public->get($file->file_path));
                $public->delete($file->file_path);
            }

            $moved++;
        }

        $this->newLine();
        $this->info($dry
            ? "{$moved} file(s) would move, {$already} already private."
            : "{$moved} file(s) moved, {$already} already private.");

        if ($missing) {
            $this->warn(count($missing).' row(s) point at a file that is not on either disk:');
            foreach ($missing as $path) {
                $this->line('  '.$path);
            }
            $this->line('Those rows are left alone; their Download button will say the file is gone.');
        }

        return self::SUCCESS;
    }
}
