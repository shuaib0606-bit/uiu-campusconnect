<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups become a family of three rather than one thing.
 *
 * - open   : made by a student, joined by asking. What existed before.
 * - course : one per course section, roster from enrolment, no join button.
 * - club   : one per club, roster from club_members.
 *
 * `posting_mode` is the teacher's switch: a class starts announcements-only
 * and becomes a conversation when the teacher decides it should.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_groups', function (Blueprint $table) {
            $table->string('kind', 20)->default('open')->after('group_type');

            // course_sections.id or clubs.id, depending on kind. Left without
            // a foreign key because it points at two different tables; the
            // sync commands are what keep it honest.
            $table->unsignedBigInteger('source_id')->nullable()->after('kind');

            $table->string('posting_mode', 20)->default('everyone')->after('visibility');
            $table->timestamp('archived_at')->nullable()->after('posting_mode');

            $table->index(['kind', 'source_id']);
        });

        Schema::table('community_group_files', function (Blueprint $table) {
            // 'material' is a slide deck or a reading; 'notice' is an
            // announcement, which may be words only.
            $table->string('kind', 20)->default('material')->after('group_id');
            $table->text('body')->nullable()->after('title');
            $table->string('file_path', 500)->nullable()->change();
            $table->string('original_name', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('community_group_files', function (Blueprint $table) {
            $table->dropColumn(['kind', 'body']);
        });

        Schema::table('community_groups', function (Blueprint $table) {
            $table->dropIndex(['kind', 'source_id']);
            $table->dropColumn(['kind', 'source_id', 'posting_mode', 'archived_at']);
        });
    }
};
