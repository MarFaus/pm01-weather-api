<?php

namespace App\Services;

use App\Support\WeatherCode;
use Composer\CaBundle\CaBundle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Клиент Open-Meteo: поиск города (Geocoding API) и прогноз (Forecast API).
 *
 * Защита от сбоев внешнего API:
 *  - таймаут и повторные попытки у каждого запроса;
 *  - свежий прогноз кешируется на cache_minutes, повторные запросы не идут в сеть;
 *  - последний удачный прогноз хранится без срока: если API не ответил,
 *    показываем его с пометкой «устаревшие данные», а не ошибку.
 */
class OpenMeteo
{
    /**
     * Найти город. null, если такого нет.
     *
     * @return array{name: string, country: ?string, region: ?string, latitude: float, longitude: float, timezone: string}|null
     */
    public function findCity(string $query): ?array
    {
        $query = trim($query);
        $key = 'geo:'.mb_strtolower($query);

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $data = $this->get(config('weather.geocoding_url'), [
            'name' => $query,
            'count' => 1,
            'language' => 'ru',
            'format' => 'json',
        ]);

        $found = $data['results'][0] ?? null;
        $city = $found === null ? null : [
            'name' => $found['name'],
            'country' => $found['country'] ?? null,
            'region' => $found['admin1'] ?? null,
            'latitude' => (float) $found['latitude'],
            'longitude' => (float) $found['longitude'],
            'timezone' => $found['timezone'] ?? 'auto',
        ];

        // Координаты городов не меняются: храним месяц. «Не найдено» тоже кешируем, но ненадолго.
        Cache::put($key, $city, $city ? now()->addDays(30) : now()->addMinutes(10));

        return $city;
    }

    /**
     * Прогноз для города: текущая погода и 7 дней.
     * source: api (только что из сети), cache (свежий кеш), stale (сеть недоступна, последний удачный ответ).
     *
     * @return array{source: string, fetched_at: string, current: array, days: list<array>}
     */
    public function forecast(array $city): array
    {
        $id = round($city['latitude'], 2).','.round($city['longitude'], 2);
        $freshKey = "forecast:{$id}";
        $lastKey = "forecast:last:{$id}";

        if ($cached = Cache::get($freshKey)) {
            return ['source' => 'cache'] + $cached;
        }

        try {
            $data = $this->get(config('weather.forecast_url'), [
                'latitude' => $city['latitude'],
                'longitude' => $city['longitude'],
                'timezone' => $city['timezone'],
                'forecast_days' => 7,
                'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,wind_speed_10m,weather_code',
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,wind_speed_10m_max',
                'wind_speed_unit' => 'ms',
            ]);

            $result = ['fetched_at' => now()->toIso8601String()] + $this->normalize($data);

            Cache::put($freshKey, $result, now()->addMinutes(config('weather.cache_minutes')));
            Cache::forever($lastKey, $result);

            return ['source' => 'api'] + $result;
        } catch (ConnectionException|RequestException $e) {
            if ($last = Cache::get($lastKey)) {
                return ['source' => 'stale'] + $last;
            }

            throw $e;
        }
    }

    private function get(string $url, array $query): array
    {
        return Http::acceptJson()
            // PHP для Windows не знает корневых сертификатов, и cURL падает с ошибкой 60.
            // CaBundle берёт системный список, а если его нет, то встроенный в пакет.
            ->withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()])
            ->timeout(config('weather.timeout'))
            ->retry(config('weather.retries'), 300)
            ->get($url, $query)
            ->throw()
            ->json();
    }

    private function normalize(array $data): array
    {
        $c = $data['current'];
        $d = $data['daily'];

        $days = [];
        foreach ($d['time'] as $i => $date) {
            $code = $d['weather_code'][$i] ?? null;
            $days[] = [
                'date' => $date,
                'max' => (int) round($d['temperature_2m_max'][$i]),
                'min' => (int) round($d['temperature_2m_min'][$i]),
                'precipitation' => $d['precipitation_probability_max'][$i] ?? null,
                'wind' => isset($d['wind_speed_10m_max'][$i]) ? (int) round($d['wind_speed_10m_max'][$i]) : null,
                'code' => $code,
                'label' => WeatherCode::label($code),
                'group' => WeatherCode::group($code),
            ];
        }

        return [
            'current' => [
                'time' => $c['time'],
                'temperature' => (int) round($c['temperature_2m']),
                'feels_like' => (int) round($c['apparent_temperature']),
                'humidity' => $c['relative_humidity_2m'],
                'wind' => round($c['wind_speed_10m'], 1),
                'code' => $c['weather_code'],
                'label' => WeatherCode::label($c['weather_code']),
                'group' => WeatherCode::group($c['weather_code']),
            ],
            'days' => $days,
        ];
    }
}
