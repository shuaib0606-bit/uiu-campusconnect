<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('slug', 190)->unique();
            $table->string('category', 80);
            $table->text('description');
            $table->string('logo_path', 500)->nullable();
            $table->string('cover_path', 500)->nullable();
            $table->string('meeting_time', 120)->nullable();
            $table->string('meeting_place', 150)->nullable();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('club_members', function (Blueprint $table) {
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('club_role', 80)->default('member');
            $table->boolean('can_post_events')->default(false);
            $table->timestamp('joined_at')->useCurrent();

            $table->primary(['club_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('club_membership_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['club_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_membership_requests');
        Schema::dropIfExists('club_members');
        Schema::dropIfExists('clubs');
    }
};
