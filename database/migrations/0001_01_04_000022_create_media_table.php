<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extra images for events, listings, clubs and notices. One row per file. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('mediable_type', 190);
            $table->unsignedBigInteger('mediable_id');
            $table->string('path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('caption', 190)->nullable();
            // Position 0 is the cover the listing pages show.
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['mediable_type', 'mediable_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
