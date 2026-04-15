<?php

namespace App\Events;

use App\Models\Prestation;
use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;

class BookingRequested
{
    use Dispatchable;

    public function __construct(
        public Reservation $reservation,
        public Prestation $prestation,
        public array $validatedData,
    ) {}
}
