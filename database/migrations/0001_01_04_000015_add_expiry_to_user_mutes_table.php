<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_mutes', function (Blueprint $table) {
            // Null means the mute never expires.
            $table->timestamp('expires_at')->nullable()->after('muted_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_mutes', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
