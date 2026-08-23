<?php

namespace App\Exceptions\Sms;

use RuntimeException;

/** Reessayer ne changerait rien : numero invalide, expediteur refuse, cle absente. */
class SmsPermanentFailure extends RuntimeException {}
