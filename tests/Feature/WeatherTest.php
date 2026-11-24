<?php

namespace Tests\Feature;

use App\Models\Search;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Open-Meteo в тестах не вызывается: все ответы подменены через Http::fake. */
class WeatherTest extends TestCase
{
    use RefreshDatabase;

    private const GEO = 'https://geocoding-api.open-meteo.com/*';
    private const FORECAST = 'https://api.open-meteo.com/*';

    protected function setUp(): void
    {
        parent::setUp();
        config(['weather.retries' => 1]);
        Http::preventStrayRequests();
    }

    private function geo(): array
    {
        return ['results' => [[
            'name' => 'Алматы', 'country' => 'Казахстан', 'admin1' => 'Алматы',
            'latitude' => 43.25, 'longitude' => 76.92, 'timezone' => 'Asia/Almaty',
        ]]];
    }

    private function forecast(float $temp = 12.6): array
    {
        $dates = ['2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27', '2026-11-28', '2026-11-29', '2026-11-30'];

        return [
            'current' => [
                'time' => '2026-11-24T12:00', 'temperature_2m' => $temp, 'apparent_temperature' => 11.2,
                'relative_humidity_2m' => 80, 'wind_speed_10m' => 0.9, 'weather_code' => 3,
            ],
            'daily' => [
                'time' => $dates,
                'weather_code' => [51, 45, 45, 71, 3, 3, 71],
                'temperature_2m_max' => [16, 10, 14, 10, 6, 8, 5],
                'temperature_2m_min' => [8, 5, 5, 3, 3, 2, 1],
                'precipitation_probability_max' => [99, 66, 12, 100, 4, 8, 51],
                'wind_speed_10m_max' => [2, 2, 4, 3, 2, 3, 2],
            ],
        ];
    }

    public function test_page_shows_forecast_and_remembers_search(): void
    {
        Http::fake([self::GEO => Http::response($this->geo()), self::FORECAST => Http::response($this->forecast())]);

        $this->get('/?city=Алматы')
            ->assertOk()
            ->assertSee('Алматы')
            ->assertSee('+13°')
            ->assertSee('Пасмурно')
            ->assertSee('только что получены');

        $this->assertSame(1, Search::where('city', 'Алматы')->value('hits'));
    }

    public function test_second_request_uses_cache(): void
    {
        Http::fake([self::GEO => Http::response($this->geo()), self::FORECAST => Http::response($this->forecast())]);

        $this->get('/?city=Алматы')->assertOk();
        $this->get('/?city=Алматы')->assertOk()->assertSee('Данные из кеша');

        Http::assertSentCount(2); // геокодинг и прогноз по разу, второй заход без сети
        $this->assertSame(2, Search::where('city', 'Алматы')->value('hits'));
    }

    public function test_stale_forecast_is_shown_when_api_is_down(): void
    {
        Http::fake([
            self::GEO => Http::response($this->geo()),
            self::FORECAST => Http::sequence()->push($this->forecast())->whenEmpty(Http::response('down', 500)),
        ]);
        $this->get('/?city=Алматы')->assertOk();

        // Свежий кеш истёк, а API упал: показываем последний удачный прогноз.
        Cache::forget('forecast:43.25,76.92');

        $this->get('/?city=Алматы')
            ->assertOk()
            ->assertSee('+13°')
            ->assertSee('Open-Meteo не отвечает');
    }

    public function test_error_message_when_api_is_down_and_no_cache(): void
    {
        Http::fake([self::GEO => Http::response('down', 503)]);

        $this->get('/?city=Алматы')
            ->assertOk()
            ->assertSee('Прогноз недоступен')
            ->assertSee('Open-Meteo сейчас не отвечает');

        $this->assertSame(0, Search::count());
    }

    public function test_unknown_city(): void
    {
        Http::fake([self::GEO => Http::response(['generationtime_ms' => 0.1])]);

        $this->get('/?city=Ыыыыы')->assertOk()->assertSee('не найден');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'api.open-meteo.com/v1/forecast'));
    }

    public function test_json_api_status_codes(): void
    {
        Http::fake([self::GEO => Http::response($this->geo()), self::FORECAST => Http::response($this->forecast())]);

        $this->getJson('/api/weather?city=Алматы')
            ->assertOk()
            ->assertJsonPath('city.name', 'Алматы')
            ->assertJsonPath('forecast.current.temperature', 13)
            ->assertJsonCount(7, 'forecast.days');

        $this->getJson('/api/weather')->assertUnprocessable();
    }

    public function test_json_api_returns_503_when_api_is_down(): void
    {
        Http::fake([self::GEO => Http::response('down', 503)]);

        $this->getJson('/api/weather?city=Алматы')->assertStatus(503)->assertJsonPath('city', null);
    }

    public function test_city_name_is_limited(): void
    {
        Http::fake();

        $this->get('/?city='.str_repeat('а', 81))->assertSessionHasErrors('city');
        Http::assertNothingSent();
    }
}
