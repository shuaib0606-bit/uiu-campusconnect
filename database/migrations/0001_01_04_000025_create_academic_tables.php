<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The academic records the course groups are built from. Nothing in the system
 * knew who was enrolled in what; these three tables are that missing piece.
 *
 * Every row carries `source` and `external_id`. Today the source is a CSV the
 * registrar exports; when the university exposes an API, a new sync writes to
 * the same tables with source='api' and its own identifiers, and nothing above
 * this layer has to change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trimesters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 30)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index('ends_on');
        });

        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trimester_id')->constrained()->cascadeOnDelete();
            $table->string('course_code', 30);
            $table->string('course_title', 190);
            $table->string('section_name', 30);
            $table->string('schedule_pattern', 60)->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('room', 120)->nullable();
            $table->unsignedInteger('seat_limit')->nullable();
            $table->string('source', 20)->default('csv');
            $table->string('external_id', 120)->nullable();
            $table->timestamps();

            // One section of one course per trimester, whatever the source.
            $table->unique(['trimester_id', 'course_code', 'section_name']);
            $table->index('external_id');
        });

        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // student, teacher or assistant.
            $table->string('role', 20)->default('student');

            // active or dropped. A dropped row is kept rather than deleted, so
            // a re-import does not silently put a removed student back in.
            $table->string('status', 20)->default('active');

            $table->string('source', 20)->default('csv');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('dropped_at')->nullable();

            $table->unique(['section_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('trimesters');
    }
};
