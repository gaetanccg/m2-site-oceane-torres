<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;

class GalleryPolicy
{
    /**
     * Anyone can view a public gallery.
     * Private galleries require a valid access token.
     */
    public function view(?User $user, Gallery $gallery, ?string $token = null): bool
    {
        if ($gallery->type === 'public') {
            return true;
        }

        if ($gallery->type === 'event' && $gallery->is_published) {
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
