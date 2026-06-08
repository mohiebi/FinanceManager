<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Morilog\Jalali\Jalalian;

class DateFormatter
{
    public static function format(CarbonInterface $date, ?string $calendar = null, string $format = 'Y-m-d'): string
    {
        if ($calendar !== 'jalali') {
            return $date->format($format);
        }

        return Jalalian::fromCarbon($date)->format($format);
    }
}
