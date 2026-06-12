<?php

namespace Tests\Unit;

use App\Support\DateTimeDisplay;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DateTimeDisplayTest extends TestCase
{
    public function test_formats_utc_timestamp_in_fixed_central_daylight_time(): void
    {
        $dateTime = CarbonImmutable::parse('2026-06-12 22:50:20', 'UTC');

        $this->assertSame('Fri, 12 Jun 2026, 5:50 PM', DateTimeDisplay::format($dateTime));
    }

    public function test_returns_placeholder_for_null(): void
    {
        $this->assertSame('--', DateTimeDisplay::format(null));
    }

    public function test_accepts_parseable_timestamp_strings(): void
    {
        $this->assertSame('Fri, 12 Jun 2026, 5:50 PM', DateTimeDisplay::format('2026-06-12 22:50:20 UTC'));
    }
}
