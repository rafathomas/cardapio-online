<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();

            $table->string('code', 20);
            $table->string('customer_name');
            $table->string('customer_phone', 20)->nullable();
            $table->string('delivery_type', 20)->default('pickup'); // pickup | delivery
            $table->string('address_line')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->text('notes')->nullable();

            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('total_cents');

            // received | preparing | ready | delivered | canceled
            $table->string('status', 20)->default('received')->index();
            $table->string('channel', 20)->default('whatsapp');

            $table->text('whatsapp_message')->nullable();
            $table->string('customer_ip', 45)->nullable();

            $table->timestamps();

            $table->unique(['establishment_id', 'code']);
            $table->index(['establishment_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot: o pedido nao muda se o produto for editado depois.
            $table->string('product_name');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('addons_total_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->json('addons')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
