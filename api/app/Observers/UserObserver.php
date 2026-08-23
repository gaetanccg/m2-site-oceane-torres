<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     * Crée automatiquement un Client lié au User
     */
    public function created(User $user): void
    {
        // Ne pas créer de client pour les admins
        if ($user->role === 'admin') {
            return;
        }

        // Vérifier si un client existe déjà avec cet email
        $existingClient = Client::where('email', $user->email)->first();

        if ($existingClient) {
            // Lier le client existant à ce user
            $existingClient->update(['user_id' => $user->id]);
        } else {
            // Créer un nouveau client
            // RGPD: Le consentement est vérifié et requis dans AuthController::register()
            // avant la création du User. Le consentement est donc explicite et tracé.
            Client::create([
                'user_id' => $user->id,
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'phone' => $user->phone,
                'source' => 'registration',
                'gdpr_consent' => true,
                'gdpr_consent_at' => now(),
            ]);
        }
    }

    /**
     * Handle the User "updated" event.
     * Synchronise les informations avec le Client lié
     */
    public function updated(User $user): void
    {
        $client = Client::where('user_id', $user->id)->first();

        if ($client) {
            $client->update([
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'phone' => $user->phone,
            ]);
        }
    }
}
