<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empties every content table and removes every account except the
 * administrators, so the site can be refilled with real data.
 */
class WipeDemoData extends Command
{
    protected $signature = 'uiu:wipe-demo {--force : Skip the confirmation prompt}';

    protected $description = 'Remove all seeded content and all non-admin accounts';

    /** Emptied completely. Order does not matter because keys are switched off. */
    private const CONTENT_TABLES = [
        'posts', 'post_media', 'post_edit_history', 'post_reactions', 'post_saves',
        'post_shares', 'hidden_posts', 'post_views', 'poll_options', 'poll_votes',
        'comments', 'comment_likes', 'mentions',
        'friendships', 'user_follows', 'user_blocks', 'user_mutes',
        'messages', 'message_blocks', 'connection_applications',
        'clubs', 'club_members', 'club_membership_requests',
        'events', 'event_registrations', 'event_interests', 'event_checkins',
        'notices', 'notice_attachments', 'section_announcements',
        'community_groups', 'community_group_members', 'community_group_files',
        'marketplace_items', 'lost_found_items', 'lost_found_claims',
        'reports', 'comment_reports', 'profile_reports', 'moderation_notes',
        'audit_logs', 'notifications', 'password_reset_requests',
    ];

    public function handle(): int
    {
        $admins = User::where('role', 'admin')->count();
        $others = User::where('role', '!=', 'admin')->count();

        if ($admins === 0) {
            $this->error('No administrator account exists. Create one before wiping, or you will be locked out.');

            return self::FAILURE;
        }

        $this->warn("This removes all seeded content and {$others} accounts. {$admins} administrator account(s) will be kept.");

        if (! $this->option('force') && ! $this->confirm('Continue?')) {
            $this->line('Nothing was changed.');

            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();

        $emptied = 0;

        foreach (self::CONTENT_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $this->warn("Skipped missing table {$table}.");

                continue;
            }

            DB::table($table)->truncate();
            $emptied++;
        }

        DB::table('users')->where('role', '!=', 'admin')->delete();
        DB::table('user_profiles')
            ->whereNotIn('user_id', fn ($q) => $q->select('id')->from('users'))
            ->delete();

        Schema::enableForeignKeyConstraints();

        Cache::flush();

        $this->newLine();
        $this->info("Emptied {$emptied} tables and removed {$others} accounts.");
        $this->line('Next: php artisan uiu:import-faculty <path-to-csv>');

        return self::SUCCESS;
    }
}
