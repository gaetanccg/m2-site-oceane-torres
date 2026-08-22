<?php

namespace Database\Seeders;

use App\Models\Prestation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    private const LOCAL_DB_HOSTS = [
        '127.0.0.1', 'localhost', '::1',
        'postgres', 'postgres-dev', 'postgres-test',
        'ot-postgres-dev', 'ot-postgres-test',
    ];

    public function run(): void
    {
        $this->assertTargetIsLocal();

        $adminPassword = $this->resolveSeedPassword('SEED_ADMIN_PASSWORD');
        $clientPassword = $this->resolveSeedPassword('SEED_CLIENT_PASSWORD');

        // Create admin user
        User::create([
            'first_name' => 'Océane',
            'last_name' => 'Torres',
            'email' => 'admin@oceanetorres.fr',
            'phone' => '+33 6 00 00 00 00',
            'password' => Hash::make($adminPassword),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create test client
        User::create([
            'first_name' => 'Client',
            'last_name' => 'Test',
            'email' => 'client@test.com',
            'phone' => '+33 6 11 11 11 11',
            'password' => Hash::make($clientPassword),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $this->command?->newLine();
        $this->command?->warn('Comptes de développement créés :');
        $this->command?->line("  admin@oceanetorres.fr  →  {$adminPassword}");
        $this->command?->line("  client@test.com        →  {$clientPassword}");
        $this->command?->newLine();

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

    private function assertTargetIsLocal(): void
    {
        $connection = config('database.default');
        $host = (string) config("database.connections.{$connection}.host");
        $database = (string) config("database.connections.{$connection}.database");

        if (in_array($host, self::LOCAL_DB_HOSTS, true)) {
            return;
        }

        throw new RuntimeException(
            "Seeder refusé : la connexion « {$connection} » cible l'hôte « {$host} » "
            ."(base « {$database} »), qui n'est pas reconnu comme local.\n"
            .'Hôtes acceptés : '.implode(', ', self::LOCAL_DB_HOSTS)
        );
    }

    private function resolveSeedPassword(string $key): string
    {
        $configured = env($key);

        return is_string($configured) && $configured !== ''
            ? $configured
            : Str::random(24);
    }
}
