<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an answer was about.
 *
 * The model only sees the text of earlier answers, not the tool results
 * behind them, so "send me her profile" after a marketplace answer used
 * to depend on the model having written the seller's link into its reply.
 * refs keeps the people and things each answer's tools returned - a
 * label and a link, nothing else - so the next question can be matched
 * to them whatever the reply happened to say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->json('refs')->nullable()->after('tools_used');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn('refs');
        });
    }
};
