<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class Time
{
    public static function toEpochMs(?Carbon $time): ?int
    {
        return $time ? (int) $time->getPreciseTimestamp(3) : null;
    }
}
