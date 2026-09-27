<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sets photo_path for every marketplace / lost & found post the user supplied a
 * real photo for, matched to the live post title. Supersedes the earlier
 * partial item seeders. Idempotent.
 * Run: php artisan db:seed --class=ItemPhotosSeeder
 */
class ItemPhotosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('marketplace_items')->where('title', "Calculus Early Transcendentals, 8th edition")->update(['photo_path' => "marketplace/calculus-early-transcendentals-8th-edition.jpg"]);
        DB::table('marketplace_items')->where('title', "Introduction to Algorithms (CLRS)")->update(['photo_path' => "marketplace/introduction-to-algorithms-clrs.jpg"]);
        DB::table('marketplace_items')->where('title', "Casio fx-991EX ClassWiz")->update(['photo_path' => "marketplace/casio-fx-991ex-classwiz.jpg"]);
        DB::table('marketplace_items')->where('title', "Casio fx-100MS")->update(['photo_path' => "marketplace/casio-fx-100ms.jpg"]);
        DB::table('marketplace_items')->where('title', "Lenovo ThinkPad T480")->update(['photo_path' => "marketplace/lenovo-thinkpad-t480.jpg"]);
        DB::table('marketplace_items')->where('title', "Dell Inspiron 15 charger")->update(['photo_path' => "marketplace/dell-inspiron-15-charger.jpg"]);
        DB::table('marketplace_items')->where('title', "Cycle helmet, medium")->update(['photo_path' => "marketplace/cycle-helmet-medium.jpg"]);
        DB::table('marketplace_items')->where('title', "Bicycle rear light and lock set")->update(['photo_path' => "marketplace/bicycle-rear-light-and-lock-set.jpg"]);
        DB::table('marketplace_items')->where('title', "Desk lamp with clamp")->update(['photo_path' => "marketplace/desk-lamp-with-clamp.jpg"]);
        DB::table('marketplace_items')->where('title', "Scientific drawing set")->update(['photo_path' => "marketplace/scientific-drawing-set.jpg"]);
        DB::table('marketplace_items')->where('title', "Acoustic guitar, beginner")->update(['photo_path' => "marketplace/acoustic-guitar-beginner.jpg"]);
        DB::table('marketplace_items')->where('title', "Lab coat, size M")->update(['photo_path' => "marketplace/lab-coat-size-m.jpg"]);
        DB::table('marketplace_items')->where('title', "Wireless mouse")->update(['photo_path' => "marketplace/wireless-mouse.jpg"]);
        DB::table('marketplace_items')->where('title', "Old Nokia phone for parts")->update(['photo_path' => "marketplace/old-nokia-phone-for-parts.jpg"]);
        DB::table('marketplace_items')->where('title', "Statistics for Engineers, 5th edition")->update(['photo_path' => "marketplace/statistics-for-engineers-5th-edition.jpg"]);
        DB::table('marketplace_items')->where('title', "Second hand printer, Canon")->update(['photo_path' => "marketplace/second-hand-printer-canon.jpg"]);
        DB::table('lost_found_items')->where('title', "Black umbrella")->update(['photo_path' => "lost-found/black-umbrella.jpg"]);
        DB::table('lost_found_items')->where('title', "Silver wristwatch")->update(['photo_path' => "lost-found/silver-wristwatch.jpg"]);
        DB::table('lost_found_items')->where('title', "Set of three keys on a red ring")->update(['photo_path' => "lost-found/set-of-three-keys-on-a-red-ring.jpg"]);
        DB::table('lost_found_items')->where('title', "Casio calculator, name written inside the cover")->update(['photo_path' => "lost-found/casio-calculator-name-written-inside-the-cover.jpg"]);
        DB::table('lost_found_items')->where('title', "Spectacles in a brown case")->update(['photo_path' => "lost-found/spectacles-in-a-brown-case.jpg"]);
        DB::table('lost_found_items')->where('title', "Blue water bottle")->update(['photo_path' => "lost-found/blue-water-bottle.jpg"]);
        DB::table('lost_found_items')->where('title', "Notebook with EEE lab reports")->update(['photo_path' => "lost-found/notebook-with-eee-lab-reports.jpg"]);
        DB::table('lost_found_items')->where('title', "Black earphones case")->update(['photo_path' => "lost-found/black-earphones-case.jpg"]);
        DB::table('lost_found_items')->where('title', "Library card")->update(['photo_path' => "lost-found/library-card.jpg"]);
        DB::table('lost_found_items')->where('title', "USB pen drive, 32GB")->update(['photo_path' => "lost-found/usb-pen-drive-32gb.jpg"]);
        DB::table('lost_found_items')->where('title', "Blue cap")->update(['photo_path' => "lost-found/blue-cap.jpg"]);
        DB::table('lost_found_items')->where('title', "Rain jacket, navy")->update(['photo_path' => "lost-found/rain-jacket-navy.jpg"]);
        DB::table('lost_found_items')->where('title', "Charger cable, USB-C")->update(['photo_path' => "lost-found/charger-cable-usb-c.jpg"]);
        DB::table('lost_found_items')->where('title', "Pencil case with a cat print")->update(['photo_path' => "lost-found/pencil-case-with-a-cat-print.jpg"]);
        DB::table('lost_found_items')->where('title', "Grey hoodie, size L")->update(['photo_path' => "lost-found/grey-hoodie-size-l.jpg"]);
    }
}
