<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('post_type', 30)->default('standard');
            $table->string('media_type', 20)->default('none');
            $table->string('media_path', 500)->nullable();
            $table->string('media_thumb_path', 500)->nullable();
            $table->string('visibility', 30)->default('uiu');
            $table->boolean('is_deleted')->default(false);
            $table->string('deleted_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['is_deleted', 'created_at']);
        });

        Schema::create('post_edit_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->text('previous_body');
            $table->timestamp('edited_at')->useCurrent();
        });

        Schema::create('post_reactions', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 20)->default('like');
            $table->timestamps();
            $table->primary(['post_id', 'user_id']);
        });

        Schema::create('post_saves', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['post_id', 'user_id']);
        });

        Schema::create('post_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('hidden_posts', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['post_id', 'user_id']);
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->unsignedInteger('position')->default(0);
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->foreignId('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('hidden_posts');
        Schema::dropIfExists('post_shares');
        Schema::dropIfExists('post_saves');
        Schema::dropIfExists('post_reactions');
        Schema::dropIfExists('post_edit_history');
        Schema::dropIfExists('posts');
    }
};
