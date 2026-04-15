<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /**
     * User can view their own reservation.
     */
    public function view(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id;
    }

    /**
     * User can update their own reservation.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id;
    }

    /**
     * User can delete their own reservation (unless confirmed).
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        if ($reservation->status === 'confirmed') {
            return false;
        }

        return $reservation->user_id === $user->id;
    }
}
