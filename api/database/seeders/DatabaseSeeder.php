<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Prestation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'first_name' => 'Océane',
            'last_name' => 'Torres',
            'email' => 'admin@oceanetorres.fr',
            'phone' => '+33 6 00 00 00 00',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create test client
        User::create([
            'first_name' => 'Client',
            'last_name' => 'Test',
            'email' => 'client@test.com',
            'phone' => '+33 6 11 11 11 11',
            'password' => Hash::make('client123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        // Create sample prestations
        $prestations = [
            [
                'title' => 'Séance Portrait',
                'description' => 'Séance photo portrait en studio ou en extérieur. Inclut 10 photos retouchées.',
                'price' => 150.00,
                'duration' => 60,
                'category' => 'Portrait',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Séance Couple',
                'description' => 'Séance photo couple en extérieur. Inclut 15 photos retouchées.',
                'price' => 200.00,
                'duration' => 90,
                'category' => 'Couple',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Séance Famille',
                'description' => 'Séance photo famille en extérieur ou à domicile. Inclut 20 photos retouchées.',
                'price' => 250.00,
                'duration' => 120,
                'category' => 'Famille',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'Mariage - Formule Essentielle',
                'description' => 'Couverture de la cérémonie et du vin d\'honneur. Inclut 100 photos retouchées.',
                'price' => 800.00,
                'duration' => 240,
                'category' => 'Mariage',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'title' => 'Mariage - Formule Complète',
                'description' => 'Couverture complète de la journée. Inclut 300 photos retouchées + album.',
                'price' => 1500.00,
                'duration' => 600,
                'category' => 'Mariage',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($prestations as $prestation) {
            Prestation::create($prestation);
        }
    }
}
