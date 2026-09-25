<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The academic profile each faculty member keeps on their department's
 * website - Education, Research Interests, Publications and so on - and
 * the PABX extension printed beside their room.
 *
 * The sections are stored as the website has them, one row per heading,
 * because every department names and orders them differently (CSE has
 * "Supervision", English has "Work Experience"). uiu:sync-faculty
 * replaces a person's rows wholesale on every run: the website is the
 * source for this content, so nothing here is edited on CampusConnect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('pabx', 20)->nullable()->after('room');
            $table->string('uiu_profile_url')->nullable();
            $table->timestamp('uiu_synced_at')->nullable();
        });

        Schema::create('faculty_profile_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->longText('body');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_profile_sections');

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['pabx', 'uiu_profile_url', 'uiu_synced_at']);
        });
    }
};
