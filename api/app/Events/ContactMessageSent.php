<?php

namespace App\Events;

use App\Models\ContactMessage;
use Illuminate\Foundation\Events\Dispatchable;

class ContactMessageSent
{
    use Dispatchable;

    public function __construct(
        public ContactMessage $contactMessage,
        public array $validatedData,
    ) {}
}
