<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A random, public identifier for every account.
 *
 * Profile addresses used to be /profile/{id}, and ids count up from one.
 * Anyone signed in could walk /profile/1, /profile/2, ... and collect the
 * whole university - or land on an account that is pending or suspended.
 * Addresses that name a person now use this column instead: ten random
 * characters, so knowing one address tells you nothing about any other.
 *
 * The numeric id stays the primary key and keeps every foreign key; this
 * only changes what appears in a URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('public_id', 16)->nullable()->unique()->after('id');
        });

        DB::table('users')->whereNull('public_id')->orderBy('id')->select('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('users')->where('id', $row->id)->update(['public_id' => self::fresh()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }

    private static function fresh(): string
    {
        do {
            $id = Str::lower(Str::random(10));
        } while (ctype_digit($id) || DB::table('users')->where('public_id', $id)->exists());

        return $id;
    }
};
