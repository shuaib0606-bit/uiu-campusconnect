<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A club officer can put somebody forward as an officer, but cannot make it so
 * on their own - the club's moderator has to agree. The request is a record
 * rather than a message, so it can sit in two people's Applications list, be
 * answered once, and leave a trail of who asked and who allowed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_role_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();

            // The person being put forward.
            $table->foreignId('nominee_id')->constrained('users')->cascadeOnDelete();

            // The officer who put them forward.
            $table->foreignId('nominated_by')->constrained('users')->cascadeOnDelete();

            // The designation being asked for, e.g. General Secretary.
            $table->string('club_role', 80);
            $table->text('reason')->nullable();

            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending');
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status']);
            $table->index(['nominated_by', 'status']);
            $table->index(['nominee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_role_requests');
    }
};
