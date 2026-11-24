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
                'relative_humidity_2m' => 80, 'wind_speed_10m' => 0.9, 'wind_direction_10m' => 87,
                'wind_gusts_10m' => 2.4, 'weather_code' => 3, 'uv_index' => 6.4, 'visibility' => 32160,
                'pressure_msl' => 1025.9, 'is_day' => 1,
            ],
            'hourly' => [
                'time' => array_map(fn ($h) => sprintf('2026-11-24T%02d:00', 12 + $h), range(0, 11)),
                'temperature_2m' => range(13, 2, -1),
                'weather_code' => array_fill(0, 12, 61),
                'precipitation_probability' => array_fill(0, 12, 70),
                'wind_speed_10m' => array_fill(0, 12, 3.2),
                'is_day' => array_fill(0, 12, 1),
            ],
            'daily' => [
                'time' => $dates,
                'weather_code' => [51, 45, 45, 71, 3, 3, 71],
                'temperature_2m_max' => [16, 10, 14, 10, 6, 8, 5],
                'temperature_2m_min' => [8, 5, 5, 3, 3, 2, 1],
                'precipitation_probability_max' => [99, 66, 12, 100, 4, 8, 51],
                'wind_speed_10m_max' => [2, 2, 4, 3, 2, 3, 2],
                'uv_index_max' => [2, 2, 1, 1, 2, 2, 0],
                'sunrise' => array_map(fn ($d) => "{$d}T07:56", $dates),
                'sunset' => array_map(fn ($d) => "{$d}T17:21", $dates),
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
            ->assertSee('только что получены')
            // Подробности: ветер, УФ, видимость, давление, солнце.
            ->assertSee('Тихий, восточный')
            ->assertSee('Порывы до 2.4 м/с')
            ->assertSee('высокий')
            ->assertSee('32 <small>км</small>', false)
            ->assertSee('769 <small>мм рт. ст.</small>', false)
            ->assertSee('Световой день 9 ч 25 мин')
            // Почасовой прогноз.
            ->assertSee('Ближайшие 24 часа')
            ->assertSee('Сейчас')
            ->assertSee('23:00');

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
        Cache::forget('forecast:v2:43.25,76.92');

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
            ->assertJsonCount(7, 'forecast.days')
            ->assertJsonCount(12, 'forecast.hours')
            ->assertJsonPath('forecast.current.uv', 6.4)
            ->assertJsonPath('forecast.current.visibility', 32.2)
            ->assertJsonPath('forecast.current.wind_direction', 87);

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

    public function test_theme_follows_weather_and_can_be_chosen(): void
    {
        Http::fake([self::GEO => Http::response($this->geo()), self::FORECAST => Http::response($this->forecast(31))]);

        // +31° без осадков: жара, панели «плавятся».
        $this->get('/?city=Алматы')->assertOk()->assertSee('theme-heat', false)->assertSee('class="drips"', false);
        // Ручной выбор оформления для показа.
        $this->get('/?city=Алматы&theme=rain')->assertOk()->assertSee('theme-rain', false)->assertSee('class="runner"', false);
        // Чужое значение не проходит валидацию.
        $this->get('/?city=Алматы&theme=lava')->assertSessionHasErrors('theme');
    }

    public function test_style_changes_layout_and_is_remembered(): void
    {
        Http::fake([self::GEO => Http::response($this->geo()), self::FORECAST => Http::response($this->forecast())]);

        // Выбор в настройках: ссылка с ?style= запоминает стиль в cookie.
        $this->get('/?city=Алматы&style=terminal')
            ->assertOk()
            ->assertSee('style-terminal', false)
            ->assertSee('$ weather now --city="Алматы"', false)
            ->assertSee('░', false)            // полоски температур символами
            ->assertCookie('ui_style', 'terminal');

        // Следующий заход без параметра берёт стиль из cookie.
        $this->withCookie('ui_style', 'resort')->get('/?city=Алматы')
            ->assertOk()->assertSee('style-resort', false)->assertSee('Привет из')->assertSee('class="palms"', false);

        // Пиксельный стиль рисует значки клетками.
        $this->get('/?city=Алматы&style=pixel')->assertOk()->assertSee('wx--pixel', false);
    }

    public function test_unknown_style_is_rejected(): void
    {
        Http::fake();

        $this->get('/?city=Алматы&style=gta')->assertSessionHasErrors('style');
        Http::assertNothingSent();
    }

    public function test_settings_dialog_lists_all_styles(): void
    {
        Http::fake([self::GEO => Http::response($this->geo()), self::FORECAST => Http::response($this->forecast())]);

        $page = $this->get('/?city=Алматы')->assertOk()->assertSee('id="settings"', false);
        foreach (['Стекло', 'Альманах', 'Пиксели', 'Брутал', 'Терминал', 'Курорт'] as $label) {
            $page->assertSee($label);
        }
        $page->assertSee('style-glass', false); // по умолчанию
    }
}
