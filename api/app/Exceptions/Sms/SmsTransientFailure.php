<?php

namespace App\Exceptions\Sms;

use RuntimeException;

/** Panne passagere : reseau, 5xx, limite de debit. Une reprise a du sens. */
class SmsTransientFailure extends RuntimeException {}
