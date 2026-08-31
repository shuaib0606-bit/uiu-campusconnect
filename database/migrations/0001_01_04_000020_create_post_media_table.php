<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Attachments for a post, one row per file. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('type', 20); // image | video
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            // Display order chosen by the author in the composer.
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['post_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_media');
    }
};
