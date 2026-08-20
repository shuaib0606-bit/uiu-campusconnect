<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_id_card_path', 500)->nullable()->after('avatar_initials');
            $table->text('signup_review_note')->nullable()->after('is_verified');
            $table->timestamp('signup_requested_at')->nullable()->after('signup_review_note');
            $table->foreignId('signup_reviewed_by')->nullable()->after('signup_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('signup_reviewed_at')->nullable()->after('signup_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signup_reviewed_by');
            $table->dropColumn(['student_id_card_path', 'signup_review_note', 'signup_requested_at', 'signup_reviewed_at']);
        });
    }
};
