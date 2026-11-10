<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database safely without duplicate errors.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'test@example.com'], // Match condition
            [                                // Attributes to insert or update
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ]
        );
    }
}