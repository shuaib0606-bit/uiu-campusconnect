<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Wires the real photos the user supplied to the matching LIVE posts
 * (matched to real titles, not the demo seeder). Idempotent.
 * Run: php artisan db:seed --class=RealItemPhotosSeeder
 */
class RealItemPhotosSeeder extends Seeder
{
    public function run(): void
    {
        // Marketplace
        DB::table('marketplace_items')->where('title', "Casio fx-991EX ClassWiz")->update(['photo_path' => "marketplace/casio-fx-991ex-classwiz.jpg"]);
        DB::table('marketplace_items')->where('title', "Scientific drawing set")->update(['photo_path' => "marketplace/scientific-drawing-set-drafting-board.jpg"]);
        DB::table('marketplace_items')->where('title', "Lab coat, size M")->update(['photo_path' => "marketplace/lab-coat-size-m-pharmacy.jpg"]);

        // Lost & Found
        DB::table('lost_found_items')->where('title', "Grey hoodie, size L")->update(['photo_path' => "lost-found/grey-hoodie-size-l.jpg"]);
        DB::table('lost_found_items')->where('title', "Casio calculator, name written inside the cover")->update(['photo_path' => "lost-found/black-casio-fx-991ex-with-name-sticker.jpg"]);
        DB::table('lost_found_items')->where('title', "Notebook with EEE lab reports")->update(['photo_path' => "lost-found/lab-notebook-electrical-machines.jpg"]);
        DB::table('lost_found_items')->where('title', "Set of three keys on a red ring")->update(['photo_path' => "lost-found/set-of-keys-with-a-red-keychain.jpg"]);
        DB::table('lost_found_items')->where('title', "Spectacles in a brown case")->update(['photo_path' => "lost-found/prescription-glasses-in-a-black-case.jpg"]);
    }
}
