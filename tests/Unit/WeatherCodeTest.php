<?php

namespace Tests\Unit;

use App\Support\WeatherCode;
use PHPUnit\Framework\TestCase;

class WeatherCodeTest extends TestCase
{
    public function test_codes_are_translated_and_grouped(): void
    {
        $this->assertSame('Ясно', WeatherCode::label(0));
        $this->assertSame('clear', WeatherCode::group(0));
        $this->assertSame('snow', WeatherCode::group(71));
        $this->assertSame('storm', WeatherCode::group(95));
        $this->assertSame('Нет данных', WeatherCode::label(null));
    }
}
