<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only. Prints every marketplace / lost&found / event / club row with its
 * stored image path and whether that file actually exists on the public disk.
 * Run: php artisan db:seed --class=AssetAuditSeeder
 */
class AssetAuditSeeder extends Seeder
{
    public function run(): void
    {
        $checks = [
            ['marketplace_items', 'photo_path'],
            ['lost_found_items', 'photo_path'],
            ['events', 'poster_path'],
            ['clubs', 'cover_path'],
        ];
        foreach ($checks as [$table, $col]) {
            $this->command->line('');
            $this->command->info(strtoupper($table).'  ('.$col.')');
            foreach (DB::table($table)->orderBy('id')->get() as $r) {
                $path = $r->$col;
                $exists = $path ? (Storage::disk('public')->exists($path) ? 'file OK ' : 'NO FILE') : '(null) ';
                $this->command->line(sprintf('  [%s] %-46s | %s', $exists, mb_substr($path ?? '—', 0, 44), mb_substr($r->title ?? $r->name ?? '', 0, 48)));
            }
        }
    }
}
