<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 190);
            $table->text('description');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('category', 80)->default('other');
            $table->string('condition', 30)->default('used');
            $table->string('photo_path', 500)->nullable();
            $table->string('status', 20)->default('available');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('lost_found_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // lost | found
            $table->string('title', 190);
            $table->text('description');
            $table->string('location', 190)->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['type', 'status', 'created_at']);
        });

        Schema::create('lost_found_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('lost_found_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('message', 500);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_found_claims');
        Schema::dropIfExists('lost_found_items');
        Schema::dropIfExists('marketplace_items');
    }
};
