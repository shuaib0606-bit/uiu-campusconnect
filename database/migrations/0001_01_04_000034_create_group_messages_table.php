<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat inside a group.
 *
 * A separate table from posts rather than another post_type, because the
 * two are different animals. A post carries media, links, polls, code,
 * reactions, an edit history and a visibility audience; a chat line is a
 * sentence and a timestamp, arrives a hundred times more often, and is
 * read newest-last instead of newest-first.
 *
 * Folding chat into posts would mean every feed query stepping over
 * thousands of "ok sir" rows, and every chat query dragging the post
 * machinery behind it. Two tables, each fast at its own job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('body');

            // Removed lines leave a tombstone rather than vanishing, so the
            // conversation above and below still makes sense.
            $table->boolean('is_deleted')->default(false);

            $table->timestamps();

            // Every read is "this group, after this id" - the shape both
            // the first page and the polling fallback ask for.
            $table->index(['group_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_messages');
    }
};
