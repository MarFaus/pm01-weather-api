@php
    use Illuminate\Support\Carbon;

    $days = $forecast['days'] ?? [];
    // Общая шкала недели: самая низкая и самая высокая температура за 7 дней.
    $lo = $days ? min(array_column($days, 'min')) : 0;
    $hi = $days ? max(array_column($days, 'max')) : 1;
    $span = max(1, $hi - $lo);
    $pos = fn (int $t) => round(($t - $lo) / $span * 100, 1);
    $deg = fn (int $t) => ($t > 0 ? '+' : '').$t.'°';
@endphp
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $city ? 'Погода: '.$city['name'] : 'Погода' }} — Сводка</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<main class="page">
    <header class="head">
        <p class="brand">Сводка</p>
        <form class="search" method="get" action="{{ route('weather.index') }}" role="search">
            <label class="visually-hidden" for="city">Город</label>
            <input id="city" name="city" type="search" value="{{ request('city') }}" placeholder="Город, например Алматы"
                   maxlength="80" autocomplete="off" @error('city') aria-invalid="true" @enderror>
            <button type="submit">Показать погоду</button>
        </form>
    </header>
    @error('city') <p class="alert">{{ $message }}</p> @enderror

    @if ($error)
        <section class="empty">
            <h1>Прогноз недоступен</h1>
            <p>{{ $error }}</p>
        </section>
    @elseif (! $city)
        <section class="empty">
            <h1>Город «{{ $query }}» не найден</h1>
            <p>Проверьте название. Можно писать по-русски или по-английски: «Астана», «Astana».</p>
        </section>
    @else
        @php $now = $forecast['current']; @endphp
        <section class="now now--{{ $now['group'] }}" aria-labelledby="city-name">
            <div>
                <h1 id="city-name" class="now__city">{{ $city['name'] }}</h1>
                <p class="now__place">{{ collect([$city['region'], $city['country']])->filter()->implode(', ') }}</p>
            </div>
            <p class="now__temp">{{ $deg($now['temperature']) }}</p>
            <div class="now__meta">
                <p class="now__label">{{ $now['label'] }}</p>
                <p>Ощущается как {{ $deg($now['feels_like']) }}</p>
                <p>Ветер {{ $now['wind'] }} м/с, влажность {{ $now['humidity'] }}%</p>
            </div>
        </section>

        <section class="week" aria-labelledby="week-title">
            <h2 id="week-title" class="week__title">7 дней</h2>
            <ol class="days">
                @foreach ($days as $i => $day)
                    @php $date = Carbon::parse($day['date'])->locale('ru'); @endphp
                    <li class="day">
                        <p class="day__name">{{ $i === 0 ? 'Сегодня' : $date->translatedFormat('D') }}</p>
                        <p class="day__date">{{ $date->translatedFormat('j M') }}</p>
                        <p class="day__label">{{ $day['label'] }}</p>
                        <div class="range" aria-label="от {{ $deg($day['min']) }} до {{ $deg($day['max']) }}">
                            <span class="range__min">{{ $deg($day['min']) }}</span>
                            <span class="range__track">
                                <span class="range__bar" style="left: {{ $pos($day['min']) }}%; right: {{ 100 - $pos($day['max']) }}%"></span>
                            </span>
                            <span class="range__max">{{ $deg($day['max']) }}</span>
                        </div>
                        <p class="day__extra">
                            @if ($day['precipitation'] !== null) осадки {{ $day['precipitation'] }}% @endif
                            @if ($day['wind'] !== null) · ветер до {{ $day['wind'] }} м/с @endif
                        </p>
                    </li>
                @endforeach
            </ol>
        </section>

        <p class="source">
            @switch($forecast['source'])
                @case('api') Данные только что получены из Open-Meteo. @break
                @case('cache') Данные из кеша, получены {{ Carbon::parse($forecast['fetched_at'])->locale('ru')->diffForHumans() }}. Кеш живёт {{ config('weather.cache_minutes') }} минут. @break
                @case('stale') <strong>Open-Meteo не отвечает.</strong> Показан последний полученный прогноз, он устарел: {{ Carbon::parse($forecast['fetched_at'])->locale('ru')->diffForHumans() }}. @break
            @endswitch
            JSON: <a href="{{ route('weather.api', ['city' => $city['name']]) }}">/api/weather?city={{ $city['name'] }}</a>
        </p>
    @endif

    @if ($recent->isNotEmpty())
        <nav class="recent" aria-label="Недавние города">
            <h2 class="recent__title">Недавно искали</h2>
            <ul>
                @foreach ($recent as $item)
                    <li><a href="{{ route('weather.index', ['city' => $item->city]) }}">{{ $item->city }}</a><span>{{ $item->hits }}</span></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <footer class="foot">
        ПМ01, работа с публичным API. Данные: <a href="https://open-meteo.com/">Open-Meteo</a> (без ключа, CC BY 4.0).
        Laravel {{ app()->version() }}, PHP {{ PHP_VERSION }}.
    </footer>
</main>
</body>
</html>
