<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comment_likes', function (Blueprint $table) {
            $table->string('reaction', 20)->default('like')->after('user_id'); // like | love | helpful | question
        });
    }

    public function down(): void
    {
        Schema::table('comment_likes', function (Blueprint $table) {
            $table->dropColumn('reaction');
        });
    }
};
