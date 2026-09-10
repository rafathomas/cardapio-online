<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('gateway', 30)->default('mercadopago');

            // Chave deterministica do evento: garante idempotencia do webhook.
            $table->string('event_key', 191)->unique();

            $table->string('event_type', 60)->nullable();
            $table->string('event_action', 60)->nullable();
            $table->string('gateway_resource_id')->nullable()->index();

            // received | processed | ignored | failed
            $table->string('status', 20)->default('received')->index();
            $table->text('error_message')->nullable();

            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
