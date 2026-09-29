<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a report say in the reporter's own words what is wrong.
 *
 * Choosing "Something else" as the reason used to send just those two words
 * to the moderators. The report form now asks for a description in that
 * case, stored here. Profile reports already had this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['reports', 'comment_reports'] as $name) {
            if (! Schema::hasColumn($name, 'details')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->string('details', 700)->nullable()->after('reason');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['reports', 'comment_reports'] as $name) {
            if (Schema::hasColumn($name, 'details')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropColumn('details');
                });
            }
        }
    }
};
