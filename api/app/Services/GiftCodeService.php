<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\GiftCode;

class GiftCodeService
{
    /**
     * Résout un code saisi (insensible à la casse / espaces) vers son modèle.
     * Lecture seule — ne modifie rien.
     */
    public function resolve(string $rawCode): ?GiftCode
    {
        $code = strtoupper(trim($rawCode));

        if ($code === '') {
            return null;
        }

        return GiftCode::where('code', $code)->first();
    }

    /**
     * Aperçu de validité pour un sous-total donné (hors verrou, pour le panier).
     *
     * @return array{valid: bool, reason: ?string, discount: float}
     */
    public function preview(GiftCode $code, float $subtotal): array
    {
        $reason = $this->validationError($code);

        if ($reason !== null) {
            return ['valid' => false, 'reason' => $reason, 'discount' => 0.0];
        }

        return ['valid' => true, 'reason' => null, 'discount' => $code->effectiveDiscount($subtotal)];
    }

    /**
     * Validation FINALE, à appeler DANS la transaction de checkout sur un modèle
     * déjà verrouillé (lockForUpdate). Throw si inutilisable, sinon renvoie la remise.
     */
    public function assertUsableForCheckout(GiftCode $code, float $subtotal): float
    {
        $reason = $this->validationError($code);

        if ($reason !== null) {
            throw new BusinessException($reason, 422);
        }

        return $code->effectiveDiscount($subtotal);
    }

    /** Message d'erreur métier si le code est inutilisable, null sinon. */
    private function validationError(GiftCode $code): ?string
    {
        if (! $code->is_active) {
            return 'Ce code promo n\'est plus valide.';
        }

        $now = now();

        if ($code->valid_from && $code->valid_from->isAfter($now)) {
            return 'Ce code promo n\'est pas encore actif.';
        }

        if ($code->valid_until && $code->valid_until->isBefore($now)) {
            return 'Ce code promo a expiré.';
        }

        if ($code->max_uses !== null && $code->paidCount() >= $code->max_uses) {
            return 'Ce code promo a atteint son nombre maximum d\'utilisations.';
        }

        return null;
    }
}
