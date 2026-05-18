<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom SMS templates must reference at least one of the access placeholders
 * (`{code}` or `{lien}`) — otherwise the recipient can't reach the gallery.
 */
class SmsTemplateRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        if (! is_string($value)) {
            $fail('Le modèle SMS doit être une chaîne de caractères.');

            return;
        }

        if (! str_contains($value, '{code}') && ! str_contains($value, '{lien}')) {
            $fail('Le modèle SMS doit contenir au moins {code} ou {lien} pour permettre l\'accès à la galerie.');
        }
    }
}
