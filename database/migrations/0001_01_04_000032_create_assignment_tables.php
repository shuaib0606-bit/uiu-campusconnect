<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assignments, and what students hand in for them.
 *
 * Until now a teacher wanting work submitted had to post a notice and
 * collect files by email, because nothing here recorded a deadline. That
 * gap showed up the moment the assistant was asked "do I have anything
 * due" and had nowhere to look.
 *
 * A submission is one row per student per assignment, not a pile of
 * uploads: handing in again replaces what was there, which is what
 * students expect and what keeps "who has submitted" answerable with a
 * single count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('community_groups')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('title', 190);
            $table->text('instructions')->nullable();

            // Nullable because some work is set with no fixed deadline.
            $table->dateTime('due_at')->nullable();

            // Whether anything may arrive after due_at. Late work is kept
            // and flagged rather than refused, so a teacher decides what
            // it is worth instead of the software deciding for them.
            $table->boolean('allow_late')->default(true);

            $table->unsignedSmallInteger('max_file_mb')->default(20);
            $table->string('allowed_extensions', 190)
                ->default('pdf,doc,docx,zip,rar,png,jpg,jpeg,xls,xlsx,ppt,pptx,txt,csv');

            // open | closed. Closing stops new submissions without
            // deleting anything already handed in.
            $table->string('status', 20)->default('open');

            $table->timestamps();

            $table->index(['group_id', 'due_at']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime', 120)->nullable();
            $table->text('note')->nullable();

            // Decided once, when the file arrives, rather than compared
            // against the clock on every read - so moving a deadline later
            // does not silently rewrite who was late.
            $table->boolean('is_late')->default(false);
            $table->timestamp('submitted_at')->useCurrent();

            $table->timestamps();

            // One row per student per assignment; handing in again updates it.
            $table->unique(['assignment_id', 'user_id']);
            $table->index(['user_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
    }
};
