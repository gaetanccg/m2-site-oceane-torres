<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait ClearsEventGalleriesCache
{
    protected function clearEventGalleriesCache(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Cache::forget("event_galleries_page_{$i}");
        }
    }
}
