<?php

namespace App\Http\Controllers;

use App\Models\Search;
use App\Services\OpenMeteo;
use App\Support\Theme;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeatherController extends Controller
{
    public function __construct(private OpenMeteo $api) {}

    /** Главная: форма поиска, прогноз для города и недавние запросы. */
    public function index(Request $request): View
    {
        $data = $request->validate([
            'city' => ['nullable', 'string', 'max:80'],
            // Ручной выбор оформления для показа: ?theme=rain. Без параметра тема подбирается по погоде.
            'theme' => ['nullable', 'string', 'in:'.implode(',', array_keys(Theme::LABELS))],
        ], [
            'city.max' => 'Название города не длиннее 80 символов.',
        ]);

        $query = trim($data['city'] ?? '') ?: config('weather.default_city');
        $result = $this->lookup($query);

        if ($result['city'] && isset($data['city'])) {
            Search::remember($result['city']);
        }

        $auto = $result['forecast'] ? Theme::resolve($result['forecast']['current'], $result['city']['latitude']) : 'fog';

        return view('weather.index', $result + [
            'query' => $query,
            'theme' => $data['theme'] ?? $auto,
            'autoTheme' => $auto,
            'themeForced' => isset($data['theme']),
            'night' => $result['forecast'] ? ! $result['forecast']['current']['is_day'] : false,
            'recent' => Search::orderByDesc('updated_at')->limit(8)->get(),
        ]);
    }

    /** То же в JSON: /api/weather?city=Алматы */
    public function api(Request $request): JsonResponse
    {
        $request->validate(['city' => ['required', 'string', 'max:80']]);

        $result = $this->lookup($request->string('city')->trim()->toString());
        $status = match (true) {
            $result['error'] !== null => 503,
            $result['city'] === null => 404,
            default => 200,
        };

        return response()->json($result, $status, [], JSON_UNESCAPED_UNICODE);
    }

    /** @return array{city: ?array, forecast: ?array, error: ?string} */
    private function lookup(string $query): array
    {
        try {
            $city = $this->api->findCity($query);

            return [
                'city' => $city,
                'forecast' => $city ? $this->api->forecast($city) : null,
                'error' => null,
            ];
        } catch (ConnectionException|RequestException) {
            return [
                'city' => null,
                'forecast' => null,
                'error' => 'Сервис погоды Open-Meteo сейчас не отвечает. Проверьте интернет и попробуйте через минуту.',
            ];
        }
    }
}
