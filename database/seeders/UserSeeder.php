<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Password123'),
                'role' => 'admin',
                'phone' => '+503 7000-0000',
            ]
        );

        User::updateOrCreate(
            ['email' => 'cliente@example.com'],
            [
                'name' => 'Cliente Demo',
                'password' => Hash::make('Password123'),
                'role' => 'customer',
                'phone' => '+503 7111-1111',
            ]
        );

        // Clientes adicionales de relleno.
        User::factory()->count(5)->create();
    }
}
