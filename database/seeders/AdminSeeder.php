<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Administrador da plataforma para ambientes locais.
 * Em producao, promova um usuario com `php artisan cardapio:make-admin`.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@cardapio.test'],
            [
                'name' => 'Administrador da Plataforma',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
