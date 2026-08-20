<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->enum('role', ['student', 'faculty', 'official', 'admin'])->default('student');
            $table->string('authority_level', 60)->default('member');
            $table->string('department', 150)->nullable();
            $table->string('designation', 150)->nullable();
            $table->string('student_id', 50)->nullable();
            $table->string('employee_id', 50)->nullable();
            $table->string('year', 50)->nullable();
            $table->string('status', 30)->default('active');
            $table->string('avatar_initials', 10)->default('UI');
            $table->boolean('is_verified')->default(false);
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['role', 'status']);
            $table->index('full_name');
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->string('profile_photo_path', 500)->nullable();
            $table->string('cover_photo_path', 500)->nullable();
            $table->string('room', 120)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('office_hours', 255)->nullable();
            $table->string('skills', 500)->nullable();
            $table->string('interests', 700)->nullable();
            $table->string('courses_taught', 700)->nullable();
            $table->string('research_interests', 700)->nullable();
            $table->string('privacy_level', 30)->default('uiu_only');
            $table->boolean('show_email')->default(true);
            $table->boolean('show_phone')->default(false);
            $table->boolean('show_friend_list')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
    }
};
