<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Number of actors collapsed into one notification row.
            $table->unsignedInteger('group_count')->default(1)->after('message');
            $table->index(['user_id', 'type', 'entity_type', 'entity_id', 'is_read'], 'notifications_dedupe_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_dedupe_index');
            $table->dropColumn('group_count');
        });
    }
};
