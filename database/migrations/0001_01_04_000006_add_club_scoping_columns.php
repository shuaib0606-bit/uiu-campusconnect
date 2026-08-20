<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table) {
            $table->text('note')->nullable()->after('user_id');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->after('user_id')->constrained('clubs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_id');
        });

        Schema::table('club_membership_requests', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
