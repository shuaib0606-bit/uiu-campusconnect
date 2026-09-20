<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three more things a post can be.
 *
 * - A document. post_media already carries attachments, so a document is a
 *   row with type 'file'. The one difference is the disk: images and video
 *   have to be publicly readable for <img> and <video> to work, but a PDF of
 *   somebody's notes on a connections-only post should not be downloadable by
 *   anybody holding the address. Documents go on the private disk and are
 *   served through a controller that re-checks who is asking.
 *
 * - Code. Stored in the post body with the language beside it, rather than as
 *   an attachment, so it is searchable and quotable like any other post.
 *
 * - A link preview. One row per post, filled once when the post is written.
 *   Kept in its own table because the fetch can fail and a post with no
 *   preview must still be a perfectly good post.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('code_language', 30)->nullable()->after('post_type');
        });

        Schema::table('post_media', function (Blueprint $table) {
            // Images and video live on the public disk; documents do not.
            $table->string('disk', 20)->default('public')->after('path');
            $table->string('original_name', 255)->nullable()->after('disk');
        });

        Schema::create('post_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('url', 1000);
            $table->string('title', 255)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->string('site_name', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // One preview per post: the first link in the body wins.
            $table->unique('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_links');

        Schema::table('post_media', function (Blueprint $table) {
            $table->dropColumn(['disk', 'original_name']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('code_language');
        });
    }
};
