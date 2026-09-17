<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Records when an item was reunited and who marked it, for the history list. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lost_found_items', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('status');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();

            $table->index(['status', 'resolved_at']);
        });

        // Rows settled before these columns existed. Only the poster could mark
        // one at the time, so the poster is the resolver.
        DB::table('lost_found_items')
            ->where('status', 'resolved')
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => DB::raw('updated_at'),
                'resolved_by' => DB::raw('user_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('lost_found_items', function (Blueprint $table) {
            $table->dropIndex(['status', 'resolved_at']);
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn('resolved_at');
        });
    }
};
