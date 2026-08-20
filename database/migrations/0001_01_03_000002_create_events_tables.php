<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 190);
            $table->text('description');
            $table->string('category', 80);
            $table->string('poster_path', 500)->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->string('location', 190);
            $table->unsignedInteger('capacity')->default(100);
            $table->foreignId('organizer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->string('status', 30)->default('published');
            $table->timestamps();

            $table->index(['status', 'start_datetime']);
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['event_id', 'user_id']);
        });

        Schema::create('event_interests', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['event_id', 'user_id']);
        });

        Schema::create('event_checkins', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_in_at')->useCurrent();
            $table->primary(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_checkins');
        Schema::dropIfExists('event_interests');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
    }
};
