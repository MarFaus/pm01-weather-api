<?php

return [
    // Open-Meteo: бесплатный API без ключа (есть в списке github.com/public-apis/public-apis).
    'geocoding_url' => env('WEATHER_GEOCODING_URL', 'https://geocoding-api.open-meteo.com/v1/search'),
    'forecast_url' => env('WEATHER_FORECAST_URL', 'https://api.open-meteo.com/v1/forecast'),

    // Сколько ждать ответа API и сколько раз повторить запрос.
    'timeout' => (int) env('WEATHER_TIMEOUT', 5),
    'retries' => (int) env('WEATHER_RETRIES', 2),

    // Сколько минут прогноз считается свежим.
    'cache_minutes' => (int) env('WEATHER_CACHE_MINUTES', 30),

    'default_city' => env('WEATHER_DEFAULT_CITY', 'Костанай'),
];
