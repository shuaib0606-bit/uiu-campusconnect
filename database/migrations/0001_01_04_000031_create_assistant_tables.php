<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage for the campus assistant.
 *
 * Two tables. Conversations so a student can carry on where they left
 * off, and messages, which double as the audit trail.
 *
 * The audit columns are the point of the second table. A security claim
 * nobody can check is just a claim, so every answer records which
 * provider produced it, which tools ran, and whether the output guard had
 * to remove anything. If the assistant ever does say something it should
 * not have, that row shows exactly how it got there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Taken from the opening question, so the list reads like a
            // list of questions rather than a column of timestamps.
            $table->string('title', 120)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            // Denormalised from the conversation so the audit trail can be
            // read per user without a join, and survives a conversation
            // being renamed or reassigned.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('body');

            // Audit. Null on the student's own turns.
            $table->string('provider', 30)->nullable();
            $table->string('model', 60)->nullable();
            $table->json('tools_used')->nullable();
            $table->json('redactions')->nullable();
            $table->unsignedSmallInteger('rounds')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
