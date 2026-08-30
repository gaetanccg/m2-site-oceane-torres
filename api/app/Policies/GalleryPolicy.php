<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;

class GalleryPolicy
{
    /**
     * Une galerie d'evenement PUBLIEE se consulte librement ; toute autre exige un jeton.
     *
     * Une troisieme branche existait ici : `type === 'public'` rendait vrai pour n'importe
     * qui, sans jeton. Mesure du 30/08 : ZERO galerie portait cette valeur, aucun FormRequest
     * ne l'acceptait, aucun code ne l'ecrivait — et `scopePublic()` n'avait aucun appelant.
     * Une branche de privilege dormante, qu'il suffisait d'un `update` pour armer. Retiree.
     */
    public function view(?User $user, Gallery $gallery, ?string $token = null): bool
    {
        if ($gallery->kind === Gallery::KIND_EVENT && $gallery->is_published) {
            return true;
        }

        if ($token && $gallery->isAccessible($token)) {
            return true;
        }

        // Owner or assigned user
        if ($user) {
            if ($gallery->user_id === $user->id) {
                return true;
            }
            if ($gallery->assigned_email && $gallery->assigned_email === $user->email) {
                return true;
            }
        }

        return false;
    }

    /**
     * User can download from a gallery if they have a valid token.
     */
    public function download(?User $user, Gallery $gallery, ?string $token = null): bool
    {
        return $this->view($user, $gallery, $token);
    }
}
