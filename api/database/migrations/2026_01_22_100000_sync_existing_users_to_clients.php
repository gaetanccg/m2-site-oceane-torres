<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Cette migration synchronise tous les users existants (role client)
     * avec la table clients, en complément de la migration initiale.
     */
    public function up(): void
    {
        // Récupérer tous les users avec role 'client' qui n'ont pas encore de client lié
        $users = DB::table('users')
            ->where('role', 'client')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('clients')
                    ->whereColumn('clients.user_id', 'users.id');
            })
            ->get();

        foreach ($users as $user) {
            $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            // Vérifier si un client existe déjà avec cet email
            $existingClient = DB::table('clients')->where('email', $user->email)->first();

            if ($existingClient) {
                // Lier le client existant à ce user si pas déjà lié
                if (empty($existingClient->user_id)) {
                    DB::table('clients')
                        ->where('id', $existingClient->id)
                        ->update(['user_id' => $user->id]);
                }
            } else {
                // Créer un nouveau client
                $clientId = Str::uuid()->toString();

                DB::table('clients')->insert([
                    'id' => $clientId,
                    'user_id' => $user->id,
                    'name' => $fullName ?: $user->email,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'source' => 'reservation',
                    'gdpr_consent' => true,
                    'gdpr_consent_at' => $user->created_at,
                    'created_at' => $user->created_at,
                    'updated_at' => now(),
                ]);

                // Lier les réservations de cet user au nouveau client
                DB::table('reservations')
                    ->where('user_id', $user->id)
                    ->whereNull('client_id')
                    ->update(['client_id' => $clientId]);
            }
        }
    }

    public function down(): void
    {
        // Pas de rollback nécessaire
    }
};
