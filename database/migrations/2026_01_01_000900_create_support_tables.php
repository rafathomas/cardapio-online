<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['establishment_id', 'key']);
        });

        Schema::create('qrcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('token', 32)->unique();
            $table->string('target_url', 500);
            $table->string('path')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('scans')->default(0);
            $table->timestamps();

            $table->index('establishment_id');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('establishment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['establishment_id', 'created_at']);
        });

        // Agregado diario de visualizacoes do cardapio publico.
        Schema::create('menu_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->date('viewed_on');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['establishment_id', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_views');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('qrcodes');
        Schema::dropIfExists('settings');
    }
};
