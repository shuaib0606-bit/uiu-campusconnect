<?php

namespace Database\Seeders;

use App\Models\Club;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Points clubs, events and items at their images under storage/app/public.
 * Items the user supplied real photos for are set unconditionally; everything
 * else fills only empty slots (whereNull) so no real upload is replaced.
 * Idempotent. Updated 27 Sep 2026.
 *
 * Run:  php artisan db:seed --class=AssetPathsSeeder
 * Once: php artisan storage:link
 */
class AssetPathsSeeder extends Seeder
{
    public function run(): void
    {
        // Clubs
        Club::where('slug', "model-united-nations-club")->whereNull('cover_path')->update(['cover_path' => "clubs/model-united-nations-club.jpg"]);
        Club::where('slug', "model-united-nations-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/model-united-nations-club.jpg"]);
        Club::where('slug', "uiu-app-forum")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-app-forum.jpg"]);
        Club::where('slug', "uiu-app-forum")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-app-forum.jpg"]);
        Club::where('slug', "uiu-business-club")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-business-club.jpg"]);
        Club::where('slug', "uiu-business-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-business-club.jpg"]);
        Club::where('slug', "uiu-computer-club")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-computer-club.jpg"]);
        Club::where('slug', "uiu-computer-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-computer-club.jpg"]);
        Club::where('slug', "uiu-cultural-club")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-cultural-club.jpg"]);
        Club::where('slug', "uiu-cultural-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-cultural-club.jpg"]);
        Club::where('slug', "uiu-debate-club")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-debate-club.png"]);
        Club::where('slug', "uiu-debate-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-debate-club.jpg"]);
        Club::where('slug', "uiu-electrical-electronics-club")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-electrical-electronics-club.jpg"]);
        Club::where('slug', "uiu-electrical-electronics-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-electrical-electronics-club.jpg"]);
        Club::where('slug', "uiu-english-language-forum")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-english-language-forum.jpg"]);
        Club::where('slug', "uiu-english-language-forum")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-english-language-forum.jpg"]);
        Club::where('slug', "uiu-entrepreneur-development-forum")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-entrepreneur-development-forum.jpg"]);
        Club::where('slug', "uiu-entrepreneur-development-forum")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-entrepreneur-development-forum.jpg"]);
        Club::where('slug', "uiu-marketing-forum")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-marketing-forum.jpg"]);
        Club::where('slug', "uiu-marketing-forum")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-marketing-forum.jpg"]);
        Club::where('slug', "uiu-photography-club")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-photography-club.png"]);
        Club::where('slug', "uiu-photography-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-photography-club.jpg"]);
        Club::where('slug', "uiu-robotics-club")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-robotics-club.jpg"]);
        Club::where('slug', "uiu-robotics-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-robotics-club.jpg"]);
        Club::where('slug', "uiu-science-forum")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-science-forum.jpg"]);
        Club::where('slug', "uiu-science-forum")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-science-forum.jpg"]);
        Club::where('slug', "uiu-social-service-club")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-social-service-club.jpg"]);
        Club::where('slug', "uiu-social-service-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-social-service-club.jpg"]);
        Club::where('slug', "uiu-sports-club")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-sports-club.jpg"]);
        Club::where('slug', "uiu-sports-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-sports-club.jpg"]);
        Club::where('slug', "uiu-theater-and-film-club")->whereNull('cover_path')->update(['cover_path' => "clubs/uiu-theater-and-film-club.jpg"]);
        Club::where('slug', "uiu-theater-and-film-club")->whereNull('logo_path')->update(['logo_path' => "clubs/logos/uiu-theater-and-film-club.jpg"]);

        // Events
        DB::table('events')->where('title', "UIU Intra-University Programming Contest")->whereNull('poster_path')->update(['poster_path' => "events/uiu-intra-university-programming-contest.jpg"]);
        DB::table('events')->where('title', "Hack the Campus 2026")->whereNull('poster_path')->update(['poster_path' => "events/hack-the-campus-2026.jpg"]);
        DB::table('events')->where('title', "Robotics Workshop: Line Following Robots")->whereNull('poster_path')->update(['poster_path' => "events/robotics-workshop-line-following-robots.jpg"]);
        DB::table('events')->where('title', "CV Writing and Interview Skills")->whereNull('poster_path')->update(['poster_path' => "events/cv-writing-and-interview-skills.jpg"]);
        DB::table('events')->where('title', "UIU Cultural Night 2026")->whereNull('poster_path')->update(['poster_path' => "events/uiu-cultural-night-2026.jpg"]);
        DB::table('events')->where('title', "Inter-Department Football Tournament")->whereNull('poster_path')->update(['poster_path' => "events/inter-department-football-tournament.jpg"]);
        DB::table('events')->where('title', "Photo Walk: Old Dhaka")->whereNull('poster_path')->update(['poster_path' => "events/photo-walk-old-dhaka.jpg"]);
        DB::table('events')->where('title', "Blood Donation Camp")->whereNull('poster_path')->update(['poster_path' => "events/blood-donation-camp.jpg"]);
        DB::table('events')->where('title', "Startup Pitch Night")->whereNull('poster_path')->update(['poster_path' => "events/startup-pitch-night.jpg"]);
        DB::table('events')->where('title', "MUN Delegate Training Session")->whereNull('poster_path')->update(['poster_path' => "events/mun-delegate-training-session.jpg"]);

        // Marketplace  (real photos set outright; rest keep placeholders)
        DB::table('marketplace_items')->where('title', "Data Structures & Algorithms in C++ (3rd ed.)")->whereNull('photo_path')->update(['photo_path' => "marketplace/data-structures-algorithms-in-c-3rd-ed.jpg"]);
        DB::table('marketplace_items')->where('title', "Casio fx-991EX Classwiz")->update(['photo_path' => "marketplace/casio-fx-991ex-classwiz.jpg"]);
        DB::table('marketplace_items')->where('title', "HP Pavilion 14 - i5 11th gen, 8GB, 512GB SSD")->update(['photo_path' => "marketplace/hp-pavilion-14-i5-11th-gen-8gb-512gb-ssd.jpg"]);
        DB::table('marketplace_items')->where('title', "Engineering Mechanics - Hibbeler")->update(['photo_path' => "marketplace/engineering-mechanics-hibbeler.jpg"]);
        DB::table('marketplace_items')->where('title', "Roommate wanted - Bashundhara R/A")->whereNull('photo_path')->update(['photo_path' => "marketplace/roommate-wanted-bashundhara-r-a.jpg"]);
        DB::table('marketplace_items')->where('title', "Physics tuition - HSC level")->whereNull('photo_path')->update(['photo_path' => "marketplace/physics-tuition-hsc-level.jpg"]);
        DB::table('marketplace_items')->where('title', "Bicycle - Duranta single speed")->update(['photo_path' => "marketplace/bicycle-duranta-single-speed.jpg"]);
        DB::table('marketplace_items')->where('title', "Scientific drawing set + drafting board")->update(['photo_path' => "marketplace/scientific-drawing-set-drafting-board.jpg"]);
        DB::table('marketplace_items')->where('title', "Principles of Marketing - Kotler & Armstrong")->update(['photo_path' => "marketplace/principles-of-marketing-kotler-armstrong.jpg"]);
        DB::table('marketplace_items')->where('title', "Mechanical keyboard - Redragon Kumara")->update(['photo_path' => "marketplace/mechanical-keyboard-redragon-kumara.jpg"]);
        DB::table('marketplace_items')->where('title', "Lab coat (size M) - Pharmacy")->update(['photo_path' => "marketplace/lab-coat-size-m-pharmacy.jpg"]);

        // Lost & Found
        DB::table('lost_found_items')->where('title', "Black Casio fx-991EX with name sticker")->update(['photo_path' => "lost-found/black-casio-fx-991ex-with-name-sticker.jpg"]);
        DB::table('lost_found_items')->where('title', "UIU ID card - CSE student")->whereNull('photo_path')->update(['photo_path' => "lost-found/uiu-id-card-cse-student.jpg"]);
        DB::table('lost_found_items')->where('title', "Blue Jansport backpack")->update(['photo_path' => "lost-found/blue-jansport-backpack.jpg"]);
        DB::table('lost_found_items')->where('title', "Set of keys with a red keychain")->update(['photo_path' => "lost-found/set-of-keys-with-a-red-keychain.jpg"]);
        DB::table('lost_found_items')->where('title', "Prescription glasses in a black case")->update(['photo_path' => "lost-found/prescription-glasses-in-a-black-case.jpg"]);
        DB::table('lost_found_items')->where('title', "Wireless earbuds case (white)")->update(['photo_path' => "lost-found/wireless-earbuds-case-white.jpg"]);
        DB::table('lost_found_items')->where('title', "Lab notebook - Electrical Machines")->update(['photo_path' => "lost-found/lab-notebook-electrical-machines.jpg"]);
        DB::table('lost_found_items')->where('title', "Grey hoodie, size L")->update(['photo_path' => "lost-found/grey-hoodie-size-l.jpg"]);
    }
}
