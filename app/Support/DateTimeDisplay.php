<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DateTimeDisplay
{
    private const DISPLAY_TIMEZONE = '-05:00';

    private const DISPLAY_FORMAT = 'D, d M Y, g:i A';

    public static function format(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '--';
        }

        try {
            $dateTime = $value instanceof CarbonInterface
                ? CarbonImmutable::instance($value)
                : CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return '--';
        }

        return $dateTime
            ->setTimezone(self::DISPLAY_TIMEZONE)
            ->format(self::DISPLAY_FORMAT);
    }
}
