<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');                 // Nome do estabelecimento
            $table->string('legal_name')->nullable(); // Nome comercial / razao social
            $table->string('slug')->unique();
            $table->string('segment', 40)->index();
            $table->text('description')->nullable();

            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();

            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20);
            $table->string('instagram', 60)->nullable();

            $table->string('address_street')->nullable();
            $table->string('address_number', 20)->nullable();
            $table->string('address_complement')->nullable();
            $table->string('address_district')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_state', 2)->nullable();
            $table->string('address_zipcode', 9)->nullable();

            $table->string('primary_color', 7)->default('#E11D48');
            $table->string('secondary_color', 7)->default('#0F172A');

            // manual_status: null = segue horario de funcionamento; open/closed = override
            $table->string('manual_status', 10)->nullable();
            $table->string('timezone', 64)->default('America/Sao_Paulo');

            $table->boolean('is_published')->default(false)->index();
            $table->boolean('is_indexable')->default(true);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishments');
    }
};
