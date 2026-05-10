<?php

namespace App\Exceptions;

use Exception;

/**
 * Marker exception for business-rule failures whose message is safe to surface
 * to the end user (e.g. "Le panier est vide.", "Cette galerie est cloturée.").
 *
 * Throw this instead of \Exception when the message is human-friendly and meant
 * to be displayed. Controllers and the global API exception handler forward the
 * message verbatim. Any other exception is treated as a technical failure and
 * replaced with a generic message before reaching the client.
 */
class BusinessException extends Exception
{
    public function __construct(string $message, protected int $httpStatus = 400)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
