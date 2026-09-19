<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A notice posted inside a course or club group is a real notice, not a
 * separate kind of thing. Scoping it with group_id lets one record appear both
 * inside the group and in the university notice board, where only the people
 * in that group can see it - rather than writing the same announcement twice
 * and hoping the two copies stay in step.
 *
 * notice_reads is what the unread count beside Groups is read from. A row
 * exists only once somebody has seen the notice, so the absence of a row is
 * the unread state and no backfill is needed for notices posted before this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('issuer_id')
                ->constrained('community_groups')
                ->cascadeOnDelete();
        });

        Schema::create('notice_reads', function (Blueprint $table) {
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->primary(['notice_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reads');

        Schema::table('notices', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
