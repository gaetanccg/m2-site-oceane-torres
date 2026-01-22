<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Créer des clients depuis les users avec role 'client'
        $users = DB::table('users')->where('role', 'client')->get();

        foreach ($users as $user) {
            $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            // Vérifier si un client avec cet email existe déjà
            $existingClient = DB::table('clients')->where('email', $user->email)->first();

            if (!$existingClient) {
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
                    ->update(['client_id' => $clientId]);
            }
        }

        // 2. Créer des clients depuis les réservations avec guest_email unique (sans user_id)
        $guestReservations = DB::table('reservations')
            ->whereNull('user_id')
            ->whereNotNull('guest_email')
            ->select('guest_name', 'guest_email', 'guest_phone', 'created_at')
            ->distinct('guest_email')
            ->get()
            ->unique('guest_email');

        foreach ($guestReservations as $guest) {
            // Vérifier si un client avec cet email existe déjà
            $existingClient = DB::table('clients')->where('email', $guest->guest_email)->first();

            if (!$existingClient) {
                $clientId = Str::uuid()->toString();

                DB::table('clients')->insert([
                    'id' => $clientId,
                    'user_id' => null,
                    'name' => $guest->guest_name ?: $guest->guest_email,
                    'email' => $guest->guest_email,
                    'phone' => $guest->guest_phone,
                    'source' => 'reservation',
                    'gdpr_consent' => false,
                    'gdpr_consent_at' => null,
                    'created_at' => $guest->created_at,
                    'updated_at' => now(),
                ]);

                // Lier toutes les réservations avec cet email au client
                DB::table('reservations')
                    ->where('guest_email', $guest->guest_email)
                    ->whereNull('client_id')
                    ->update(['client_id' => $clientId]);
            } else {
                // Lier les réservations au client existant
                DB::table('reservations')
                    ->where('guest_email', $guest->guest_email)
                    ->whereNull('client_id')
                    ->update(['client_id' => $existingClient->id]);
            }
        }
    }

    public function down(): void
    {
        // Supprimer les liens client_id des réservations
        DB::table('reservations')->update(['client_id' => null]);

        // Supprimer tous les clients
        DB::table('clients')->truncate();
    }
};
