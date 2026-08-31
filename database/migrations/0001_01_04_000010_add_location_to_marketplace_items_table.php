<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->string('location', 190)->nullable()->after('category');
        });

        // A null price means the listing is negotiable.
        DB::statement('ALTER TABLE marketplace_items MODIFY price DECIMAL(10,2) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->dropColumn('location');
        });

        DB::statement('UPDATE marketplace_items SET price = 0 WHERE price IS NULL');
        DB::statement('ALTER TABLE marketplace_items MODIFY price DECIMAL(10,2) NOT NULL DEFAULT 0');
    }
};
