<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files a teacher hands out with an assignment: a template to fill in, a
 * worked example, a marking rubric.
 *
 * Deliberately a separate table from assignment_submissions even though
 * the columns look alike, because the two answer opposite questions about
 * who may read them. An attachment goes from the teacher to the whole
 * class and every member may open it. A submission goes from one student
 * to the teacher and nobody else may. Keeping them apart means that
 * difference lives in the schema rather than in a flag somebody has to
 * remember to check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();

            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime', 120)->nullable();

            // What it is for, in the teacher's words: "Use this template",
            // "Last year's best report". Optional - the filename often says
            // enough on its own.
            $table->string('label', 190)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_attachments');
    }
};
