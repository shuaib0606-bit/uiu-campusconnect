<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title', 190);
            $table->text('body');
            $table->string('notice_type', 80)->default('university');
            $table->string('priority', 30)->default('normal');
            $table->string('audience', 80)->default('all');
            $table->foreignId('issuer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('published');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('section_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('course_code', 30);
            $table->string('section_name', 30);
            $table->string('title', 190);
            $table->string('schedule_pattern', 60);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 120);
            $table->unsignedInteger('seat_limit');
            $table->foreignId('issuer_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['course_code', 'section_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_announcements');
        Schema::dropIfExists('notices');
    }
};
