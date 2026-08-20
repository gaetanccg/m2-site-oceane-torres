<?php

namespace App\Services\Supervision\Probes;

use App\Services\Supervision\ProbeResult;

interface Probe
{
    public function key(): string;

    public function check(): ProbeResult;
}
