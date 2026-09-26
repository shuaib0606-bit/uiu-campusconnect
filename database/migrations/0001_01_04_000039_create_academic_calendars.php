<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UIU's published academic calendars, one per program per term.
 *
 * Until now there was a single hand-typed list in config/academic.php, so a
 * masters or B.Pharm student saw the undergraduate dates. UIU publishes a
 * separate calendar for each program (undergraduate and graduate trimesters,
 * B.Pharm semesters); `php artisan uiu:import-calendars` copies them in here.
 *
 * An imported calendar is identified by the slug of its page on uiu.ac.bd, so
 * running the import again updates it in place - "[Revised]" calendars simply
 * replace their dates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 190)->unique();

            // undergraduate | graduate | bpharm
            $table->string('program', 30);
            // "Summer 2026" - and whether UIU calls it a trimester or a semester.
            $table->string('term', 60);
            $table->string('term_kind', 20)->default('trimester');

            $table->string('title', 255);
            $table->string('source_url', 500)->nullable();
            // The footnotes under the table: instalment percentages, fines, moon-sighting caveats.
            $table->text('notes')->nullable();

            // First and last dated row, so "which calendar is running now" is one query.
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->date('published_on')->nullable();
            // Hash of the parsed rows; an unchanged calendar is left untouched on re-import.
            $table->string('checksum', 64)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['program', 'ends_on']);
        });

        Schema::create('academic_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('academic_calendars')->cascadeOnDelete();

            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('title', 500);
            // exam | payment | holiday | registration | deadline | grading | classes | event | academic
            $table->string('type', 20)->default('academic');
            // Printed in bold on the UIU page.
            $table->boolean('important')->default(false);
            $table->text('note')->nullable();
            // The date exactly as UIU wrote it, for checking the parser.
            $table->string('date_text', 80)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['calendar_id', 'starts_on']);
            $table->index(['type', 'ends_on']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Which calendar the feed shows. Null means "work it out from the department".
            $table->string('calendar_program', 30)->nullable()->after('year');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('calendar_program'));
        Schema::dropIfExists('academic_calendar_events');
        Schema::dropIfExists('academic_calendars');
    }
};
