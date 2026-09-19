<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clubs hand over their student panel as a set, not one person at a time.
 *
 * The outgoing president proposes the incoming panel; the club's moderator, or
 * a super admin, approves it. On approval every existing designation in the
 * club is stripped and the new slate takes them, which is why the proposal is
 * a record with rows rather than a series of separate promotions: it has to be
 * agreed and applied as one thing, or the club spends a week with two
 * treasurers.
 *
 * previous_panel keeps who held what immediately before the change, so a year
 * later it is still possible to say who ran the club and who signed it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_panel_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();

            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending');
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            // Who held which designation just before this was applied.
            $table->json('previous_panel')->nullable();

            $table->timestamps();

            $table->index(['club_id', 'status']);
            $table->index(['proposed_by', 'status']);
        });

        Schema::create('club_panel_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_change_id')->constrained('club_panel_changes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('club_role', 80);
            $table->unsignedSmallInteger('position')->default(0);

            $table->unique(['panel_change_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_panel_members');
        Schema::dropIfExists('club_panel_changes');
    }
};
