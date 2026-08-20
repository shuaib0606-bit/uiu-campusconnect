<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->string('department', 120)->nullable()->after('audience');
            $table->string('batch', 60)->nullable()->after('department');
            $table->string('semester', 60)->nullable()->after('batch');
            $table->boolean('is_urgent')->default(false)->after('semester');
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn(['department', 'batch', 'semester', 'is_urgent']);
        });
    }
};
