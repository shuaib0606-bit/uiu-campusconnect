<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_deleted')->default(false);
            $table->string('deleted_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['post_id', 'created_at']);
        });

        Schema::create('comment_likes', function (Blueprint $table) {
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['comment_id', 'user_id']);
        });

        Schema::create('comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 255);
            $table->string('status', 30)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('moderator_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['comment_id', 'reporter_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reports');
        Schema::dropIfExists('comment_likes');
        Schema::dropIfExists('comments');
    }
};
