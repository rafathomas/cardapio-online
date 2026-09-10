<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('min_options')->default(0);
            $table->unsignedTinyInteger('max_options')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['establishment_id', 'is_active']);
        });

        Schema::create('product_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['addon_group_id', 'is_active', 'sort_order']);
        });

        // Grupos de adicionais sao reutilizaveis entre produtos do mesmo estabelecimento.
        Schema::create('addon_group_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['addon_group_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_group_product');
        Schema::dropIfExists('product_addons');
        Schema::dropIfExists('addon_groups');
    }
};
