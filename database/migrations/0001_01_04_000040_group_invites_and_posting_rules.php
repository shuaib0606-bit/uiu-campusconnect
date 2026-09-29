<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations to a group, finer posting rules, and an approval queue.
 *
 * - group_invites: the owner of a group invites people rather than adding
 *   them. Nobody becomes a member until they accept. Kept out of the member
 *   pivot on purpose: every membership check in the app reads that pivot,
 *   and an unanswered invitation must not count as membership anywhere.
 * - community_group_members.can_post: who may post when a group is set to
 *   "selected members only".
 * - posts.approval_status: a group post waiting for the owner's approval
 *   ('pending'), or turned down ('rejected'). NULL is an ordinary post.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // One row per person per group; a new invitation reuses it.
            $table->unique(['group_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('community_group_members', function (Blueprint $table) {
            $table->boolean('can_post')->default(false)->after('role');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('approval_status', 20)->nullable()->after('group_id');
            $table->index(['group_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'approval_status']);
            $table->dropColumn('approval_status');
        });

        Schema::table('community_group_members', function (Blueprint $table) {
            $table->dropColumn('can_post');
        });

        Schema::dropIfExists('group_invites');
    }
};
