<?php

use App\Support\FacultyProfileText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Faculty can now edit their academic profile and decide who sees each part.
 *
 * Sections gain:
 *   visibility    everyone / members / only_me
 *   source        website (came from the department site) or custom
 *   source_key    the website heading it came from, lower case - how the
 *                 sync recognises the same section on the next run
 *   website_body  the latest text from the website, kept even after an
 *                 edit so "restore the website version" has something to
 *                 restore
 *   edited_at     set when the owner changes a website section; the sync
 *                 leaves such a section alone from then on
 *   removed_at    set when the owner deletes a website section, so the
 *                 next sync does not quietly bring it back
 *
 * Profiles gain a visibility level for room, PABX, office hours, courses
 * taught and research interests, and uiu_room / uiu_pabx: the last values
 * the website gave, so the sync only overwrites a room nobody changed.
 *
 * It also undoes one thing the first sync did. It copied long paragraphs
 * from the website into bio and research interests; a bio is a line or two
 * the person writes. Values that are exactly what the sync wrote are
 * cleared (research interests are kept when the website has a short list),
 * and anything a person typed themselves is left untouched.
 */
return new class extends Migration
{
    private const FIELDS = ['room_visibility', 'pabx_visibility', 'office_hours_visibility', 'courses_visibility', 'research_visibility'];

    public function up(): void
    {
        Schema::table('faculty_profile_sections', function (Blueprint $table) {
            $table->string('visibility', 10)->default('everyone')->after('position');
            $table->string('source', 10)->default('custom')->after('visibility');
            $table->string('source_key', 150)->nullable()->after('source');
            $table->longText('website_body')->nullable()->after('source_key');
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('removed_at')->nullable();
        });

        // Every section so far came from the website.
        DB::table('faculty_profile_sections')->update(['source' => 'website']);
        DB::table('faculty_profile_sections')->orderBy('id')->select('id', 'title', 'body')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('faculty_profile_sections')->where('id', $row->id)->update([
                        'source_key' => mb_strtolower(trim($row->title)),
                        'website_body' => $row->body,
                    ]);
                }
            });

        Schema::table('user_profiles', function (Blueprint $table) {
            foreach (self::FIELDS as $field) {
                $table->string($field, 10)->default('everyone');
            }

            $table->string('uiu_room', 120)->nullable();
            $table->string('uiu_pabx', 20)->nullable();
        });

        DB::table('user_profiles')->whereNotNull('uiu_synced_at')->update([
            'uiu_room' => DB::raw('room'),
            'uiu_pabx' => DB::raw('pabx'),
        ]);

        $this->undoCopiedText();
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([...self::FIELDS, 'uiu_room', 'uiu_pabx']);
        });

        Schema::table('faculty_profile_sections', function (Blueprint $table) {
            $table->dropColumn(['visibility', 'source', 'source_key', 'website_body', 'edited_at', 'removed_at']);
        });
    }

    private function undoCopiedText(): void
    {
        $profiles = DB::table('user_profiles')->whereNotNull('uiu_synced_at')
            ->where(fn ($q) => $q->whereNotNull('bio')->orWhereNotNull('research_interests'))
            ->get(['user_id', 'bio', 'research_interests']);

        foreach ($profiles as $profile) {
            $sections = DB::table('faculty_profile_sections')->where('user_id', $profile->user_id)
                ->orderBy('position')->pluck('body', 'title')->all();

            $changes = [];

            if ($profile->bio !== null && $profile->bio === FacultyProfileText::legacyBio($sections)) {
                $changes['bio'] = null;
            }

            if ($profile->research_interests !== null && $profile->research_interests === FacultyProfileText::legacyResearch($sections)) {
                $changes['research_interests'] = FacultyProfileText::researchTags($sections);
            }

            if ($changes !== []) {
                DB::table('user_profiles')->where('user_id', $profile->user_id)->update($changes);
            }
        }
    }
};
