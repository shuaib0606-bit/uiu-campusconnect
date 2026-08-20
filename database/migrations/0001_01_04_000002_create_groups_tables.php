<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 190)->unique();
            $table->text('description');
            $table->string('cover_path', 500)->nullable();
            $table->string('visibility', 20)->default('public');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('community_group_members', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->primary(['group_id', 'user_id']);
        });

        Schema::create('community_group_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_group_files');
        Schema::dropIfExists('community_group_members');
        Schema::dropIfExists('community_groups');
    }
};
