<?php

namespace Tests\Unit;

use App\Support\Conditions;
use App\Support\Theme;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
    private function now(string $group, int $temp, string $time = '2026-11-24T12:00'): array
    {
        return ['group' => $group, 'temperature' => $temp, 'time' => $time];
    }

    public function test_precipitation_wins_over_temperature(): void
    {
        $this->assertSame('rain', Theme::resolve($this->now('rain', 30), 43));
        $this->assertSame('storm', Theme::resolve($this->now('storm', 30), 43));
        $this->assertSame('snow', Theme::resolve($this->now('snow', -5), 43));
        $this->assertSame('fog', Theme::resolve($this->now('fog', 10), 43));
    }

    public function test_temperature_thresholds(): void
    {
        $this->assertSame('heat', Theme::resolve($this->now('clear', 25), 43));
        $this->assertSame('frost', Theme::resolve($this->now('cloud', 0), 43));
        $this->assertSame('autumn', Theme::resolve($this->now('cloud', 1), 43));
    }

    public function test_season_depends_on_hemisphere(): void
    {
        $april = Carbon::parse('2026-04-10');
        $this->assertSame('spring', Theme::season($april, 51.1));   // Астана
        $this->assertSame('autumn', Theme::season($april, -33.9));  // Сидней
        $this->assertSame('winter', Theme::season(Carbon::parse('2026-01-10'), 43));
        $this->assertSame('summer', Theme::season(Carbon::parse('2026-01-10'), -33.9));
    }

    public function test_conditions_are_described_in_words(): void
    {
        $this->assertSame('низкий', Conditions::uv(2.9)['label']);
        $this->assertSame('высокий', Conditions::uv(6)['label']);
        $this->assertSame('экстремальный', Conditions::uv(11)['label']);
        $this->assertSame('очень плохая, туман', Conditions::visibility(.5));
        $this->assertSame('отличная', Conditions::visibility(32));
        $this->assertSame('штиль', Conditions::wind(0.1));
        $this->assertSame('сильный', Conditions::wind(12));
        $this->assertSame('северный', Conditions::direction(355));
        $this->assertSame('В', Conditions::direction(87, true));
        $this->assertSame('юго-западный', Conditions::direction(225));
    }
}
