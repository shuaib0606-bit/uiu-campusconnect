<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a post belong to a community group, the same way it can already belong
 * to a club. A group post never appears in the feed, on a profile or in
 * search: Post::scopeVisible excludes anything with a group_id, so the group
 * page is the only place that shows them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('club_id')
                ->constrained('community_groups')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
