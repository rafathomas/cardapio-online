<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->string('billing_period', 20)->default('monthly');
            $table->unsignedInteger('trial_days')->default(0);

            // NULL significa ilimitado.
            $table->unsignedInteger('max_products')->nullable();
            $table->unsignedInteger('max_categories')->nullable();
            $table->unsignedInteger('max_establishments')->nullable();

            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
