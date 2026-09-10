<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();

            $table->string('gateway', 30)->default('mercadopago');
            $table->string('gateway_payment_id')->nullable();

            // Referencia idempotente gerada pela aplicacao e enviada ao gateway.
            $table->string('external_reference', 64)->unique();
            $table->string('idempotency_key', 64)->unique();

            // pending | in_process | approved | rejected | canceled | refunded | charged_back
            $table->string('status', 20)->index();
            $table->string('status_detail')->nullable();

            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_type', 40)->nullable();

            $table->string('checkout_url', 1000)->nullable();
            $table->text('qr_code_payload')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->json('gateway_payload')->nullable();

            $table->timestamps();

            $table->unique(['gateway', 'gateway_payment_id']);
            $table->index(['establishment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
