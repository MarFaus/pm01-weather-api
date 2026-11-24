# Сводка: погода на Open-Meteo (ПМ01, ЛР «публичное API»)

Репозиторий: https://github.com/MarFaus/pm01-weather-api (отдельный, не pm01-redis-lab).

## Запуск и тесты
- Windows: `start.bat` → `scripts/start.ps1` (5 шагов: PHP, composer, .env, SQLite+migrate, serve на свободном порту от 8000).
- Без окон и браузера: `$env:WEATHER_NONINTERACTIVE='1'` перед запуском `start.ps1`.
- Тесты: `C:\Users\Public\kassa6379-tools\php\php.exe artisan test` (10 тестов, сеть не нужна).
- Системный PHP в `Documents\php 8.3` не подходит (кириллица/пробел в пути, нет нужных расширений); берём переносной из `C:\Users\Public\kassa6379-tools` (общая папка с прошлыми ЛР).

## Решения
- Open-Meteo: без ключа, понимает русские названия городов.
- Защита от сбоев API: таймаут 5 с, retry, свежий кеш 30 мин, плюс `forecast:last:*` навсегда → источник `stale`, если сеть упала. Без копии показываем «Прогноз недоступен», API отвечает 503.
- Кеш в базе (`CACHE_STORE=database`), Redis не нужен.
- Фронт без сборки: `public/css/app.css` + blade.

## Подводные камни
- cURL error 60 на Windows PHP (нет CA). Решено через `composer/ca-bundle` в `OpenMeteo::get()`. Не убирать.
- В `Http::fake` шаблоны дополняются `*` в начале: `api.open-meteo.com/*` ловит и `geocoding-api...`. Поэтому в тестах полные `https://`-шаблоны.
- `start.bat` только ASCII; `start.ps1` в UTF-8 с BOM и CRLF (PS 5.1), это закреплено в `.gitattributes`.
- Composer запускать только как `composer.phar` тем же PHP: в колледже системный composer привязан к PHP 8.2 (XAMPP).
- CLAUDE.md из скелета Laravel (установка Boost) заменён этим файлом, Boost не ставили.

## Не сделано
- Проверено только дома; в колледже запуск ещё не прогоняли.
