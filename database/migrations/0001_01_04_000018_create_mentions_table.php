<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Resolved @-mentions on posts and comments, stored at write time. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->string('mentionable_type', 60);
            $table->unsignedBigInteger('mentionable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // One row per body and mentioned user, however often they are named.
            $table->unique(['mentionable_type', 'mentionable_id', 'user_id'], 'mentions_unique');

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};
