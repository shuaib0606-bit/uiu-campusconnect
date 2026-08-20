<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_groups', function (Blueprint $table) {
            $table->string('group_type', 20)->default('community')->after('name');
            $table->string('department', 120)->nullable()->after('description');
        });

        Schema::table('community_group_files', function (Blueprint $table) {
            $table->string('title', 190)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('community_groups', function (Blueprint $table) {
            $table->dropColumn(['group_type', 'department']);
        });

        Schema::table('community_group_files', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
