@php
    use App\Support\Conditions;
    use App\Support\Theme;
    use Illuminate\Support\Carbon;

    $days = $forecast['days'] ?? [];
    $hours = $forecast['hours'] ?? [];
    $deg = fn (int $t) => ($t > 0 ? '+' : '').$t.'°';

    // Неделя: все полоски на одной шкале, поэтому видно, какой день теплее.
    $lo = $days ? min(array_column($days, 'min')) : 0;
    $hi = $days ? max(array_column($days, 'max')) : 1;
    $span = max(1, $hi - $lo);
    $pos = fn (int $t) => round(($t - $lo) / $span * 100, 1);

    // 24 часа: линия температуры поверх колонок шириной $col пикселей.
    $col = 64;
    $hLo = $hours ? min(array_column($hours, 'temperature')) : 0;
    $hHi = $hours ? max(array_column($hours, 'temperature')) : 1;
    $hy = fn (int $t) => round(24 + (1 - ($t - $hLo) / max(1, $hHi - $hLo)) * 40, 1);
    $points = collect($hours)->map(fn ($h, $i) => ($i * $col + $col / 2).','.$hy($h['temperature']))->implode(' ');

    // Тему можно выбрать вручную (?theme=), остальные ссылки сохраняют выбор.
    $keep = $themeForced ? ['theme' => $theme] : [];
    $isNight = $night && ! in_array($theme, ['rain', 'storm'], true);
@endphp
<!doctype html>
<html lang="ru" class="theme-{{ $theme }} {{ $isNight ? 'is-night' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $city ? 'Погода: '.$city['name'] : 'Погода' }} — Сводка</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body style="--wind: {{ min(12, $forecast['current']['wind'] ?? 2) }}">
@include('weather.partials.fx')

<main class="page">
    <header class="head">
        <a class="brand" href="{{ route('weather.index', $keep) }}">Сводка</a>
        <form class="search" method="get" action="{{ route('weather.index') }}" role="search" data-search>
            <label class="visually-hidden" for="city">Город</label>
            <input id="city" name="city" type="search" value="{{ request('city') }}" placeholder="Город, например Алматы"
                   maxlength="80" autocomplete="off" @error('city') aria-invalid="true" aria-describedby="city-error" @enderror>
            @if ($themeForced) <input type="hidden" name="theme" value="{{ $theme }}"> @endif
            <button class="btn" type="submit"><span>Показать погоду</span></button>
        </form>
    </header>
    @error('city') <p class="alert" id="city-error">{{ $message }}</p> @enderror

    @if ($error)
        <section class="glass empty" role="alert">
            <h1>Прогноз недоступен</h1>
            <p>{{ $error }}</p>
            <a class="btn btn--small" href="{{ request()->fullUrl() }}"><span>Попробовать снова</span></a>
        </section>
    @elseif (! $city)
        <section class="glass empty">
            <h1>Город «{{ $query }}» не найден</h1>
            <p>Проверьте название. Можно писать по-русски или по-английски. Например:</p>
            <p class="chips">
                @foreach (['Астана', 'Алматы', 'Москва', 'Dubai'] as $example)
                    <a class="chip" href="{{ route('weather.index', ['city' => $example] + $keep) }}">{{ $example }}</a>
                @endforeach
            </p>
        </section>
    @else
        @php
            $now = $forecast['current'];
            $today = $days[0] ?? null;
            $uv = Conditions::uv($now['uv']);
            $updated = Carbon::parse($now['time']);
        @endphp

        {{-- Сейчас --}}
        <section class="glass hero" aria-labelledby="city-name">
            @includeWhen($theme === 'heat', 'weather.partials.drips', ['seed' => 1])
            @if ($theme === 'spring') <x-vine class="vine vine--tl"/> <x-vine class="vine vine--br"/> @endif

            <div class="hero__main">
                <div class="hero__place">
                    <h1 id="city-name" class="hero__city">{{ $city['name'] }}</h1>
                    <p class="muted">{{ collect([$city['region'] !== $city['name'] ? $city['region'] : null, $city['country']])->filter()->implode(', ') }}</p>
                    <p class="muted small">Местное время {{ $updated->format('H:i') }}</p>
                </div>
                <div class="hero__now">
                    <x-wx-icon :group="$now['group']" :day="$now['is_day']" :size="88" class="hero__icon"/>
                    <p class="hero__temp" aria-label="Температура {{ $deg($now['temperature']) }}">{{ $deg($now['temperature']) }}</p>
                </div>
                <div class="hero__summary">
                    <p class="hero__label">{{ $now['label'] }}</p>
                    <p class="muted">Ощущается как {{ $deg($now['feels_like']) }}</p>
                    @if ($today) <p class="muted">Днём до {{ $deg($today['max']) }}, ночью до {{ $deg($today['min']) }}</p> @endif
                </div>
            </div>

            <dl class="details">
                <div class="tile">
                    <dt>Ветер</dt>
                    <dd class="tile__value">
                        @if ($now['wind_direction'] !== null)
                            {{-- Стрелка показывает, куда дует ветер: направление «откуда» + 180°. --}}
                            <svg class="compass" viewBox="0 0 32 32" aria-hidden="true">
                                <circle cx="16" cy="16" r="14"/>
                                <path d="M16 5l5 13-5-3-5 3z" style="transform: rotate({{ ($now['wind_direction'] + 180) % 360 }}deg)"/>
                            </svg>
                        @endif
                        {{ $now['wind'] }} <small>м/с</small>
                    </dd>
                    <dd class="tile__note">
                        {{ Str::ucfirst(Conditions::wind($now['wind'])) }}@if ($now['wind_direction'] !== null), {{ Conditions::direction($now['wind_direction']) }}@endif.
                        @if ($now['gusts']) Порывы до {{ $now['gusts'] }} м/с. @endif
                    </dd>
                </div>
                <div class="tile">
                    <dt>УФ-индекс</dt>
                    <dd class="tile__value">{{ $now['uv'] ?? '—' }} <small>{{ $uv['label'] }}</small></dd>
                    <dd class="uv" aria-hidden="true"><span style="left: {{ min(100, ($now['uv'] ?? 0) / 11 * 100) }}%"></span></dd>
                    <dd class="tile__note">{{ $uv['advice'] }}@if ($today && $today['uv'] !== null). Максимум сегодня {{ $today['uv'] }}@endif</dd>
                </div>
                <div class="tile">
                    <dt>Видимость</dt>
                    <dd class="tile__value">{{ $now['visibility'] !== null ? ($now['visibility'] >= 10 ? round($now['visibility']) : $now['visibility']) : '—' }} <small>км</small></dd>
                    <dd class="tile__note">{{ Str::ucfirst(Conditions::visibility($now['visibility'])) }}</dd>
                </div>
                <div class="tile">
                    <dt>Влажность</dt>
                    <dd class="tile__value">{{ $now['humidity'] }}<small>%</small></dd>
                    <dd class="meter" aria-hidden="true"><span style="width: {{ $now['humidity'] }}%"></span></dd>
                </div>
                <div class="tile">
                    <dt>Давление</dt>
                    <dd class="tile__value">{{ $now['pressure'] ?? '—' }} <small>мм рт. ст.</small></dd>
                    @if ($now['pressure'])
                        <dd class="tile__note">{{ $now['pressure'] > 765 ? 'Выше нормы' : ($now['pressure'] < 755 ? 'Ниже нормы' : 'В норме') }} (норма 760)</dd>
                    @endif
                </div>
                @if ($today && $today['sunrise'])
                    @php [$rise, $set] = [Carbon::parse($today['sunrise']), Carbon::parse($today['sunset'])]; @endphp
                    <div class="tile">
                        <dt>Солнце</dt>
                        <dd class="tile__value">{{ $rise->format('H:i') }} <small>→</small> {{ $set->format('H:i') }}</dd>
                        <dd class="tile__note">Световой день {{ intdiv($rise->diffInMinutes($set), 60) }} ч {{ $rise->diffInMinutes($set) % 60 }} мин</dd>
                    </div>
                @endif
            </dl>
        </section>

        {{-- 24 часа --}}
        @if ($hours)
            <section class="glass hours" aria-labelledby="hours-title">
                @includeWhen($theme === 'heat', 'weather.partials.drips', ['seed' => 2])
                <h2 id="hours-title" class="section-title">Ближайшие 24 часа</h2>
                <div class="hours__scroll" tabindex="0" aria-label="Почасовой прогноз, прокручивается вбок">
                    <div class="hours__track" style="width: {{ count($hours) * $col }}px">
                        <svg class="hours__curve" width="{{ count($hours) * $col }}" height="72" aria-hidden="true">
                            <polyline points="{{ $points }}"/>
                            @foreach ($hours as $i => $h)
                                <circle cx="{{ $i * $col + $col / 2 }}" cy="{{ $hy($h['temperature']) }}" r="3"/>
                                <text x="{{ $i * $col + $col / 2 }}" y="{{ $hy($h['temperature']) - 8 }}">{{ $deg($h['temperature']) }}</text>
                            @endforeach
                        </svg>
                        <ol class="hours__list">
                            @foreach ($hours as $i => $h)
                                @php $t = Carbon::parse($h['time']); @endphp
                                <li class="hour" aria-label="{{ $i === 0 ? 'Сейчас' : $t->format('H:i') }}: {{ $deg($h['temperature']) }}, {{ mb_strtolower($h['label']) }}">
                                    <x-wx-icon :group="$h['group']" :day="$h['is_day']" :size="26"/>
                                    <span class="hour__rain">{{ $h['precipitation'] ? $h['precipitation'].'%' : '' }}</span>
                                    <span class="hour__time">{{ $i === 0 ? 'Сейчас' : $t->format('H:i') }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </section>
        @endif

        {{-- 7 дней --}}
        <section class="glass week" aria-labelledby="week-title">
            @includeWhen($theme === 'heat', 'weather.partials.drips', ['seed' => 3])
            @if ($theme === 'spring') <x-vine class="vine vine--tr"/> @endif
            <h2 id="week-title" class="section-title">7 дней</h2>
            <ol class="days">
                @foreach ($days as $i => $day)
                    @php $date = Carbon::parse($day['date'])->locale('ru'); @endphp
                    <li class="day">
                        <p class="day__name">{{ $i === 0 ? 'Сегодня' : mb_convert_case($date->isoFormat('dd'), MB_CASE_TITLE) }}
                            <span class="muted">{{ $date->isoFormat('D MMM') }}</span></p>
                        <p class="day__label"><x-wx-icon :group="$day['group']" :size="26"/> <span>{{ $day['label'] }}</span></p>
                        <div class="range" role="img" aria-label="от {{ $deg($day['min']) }} до {{ $deg($day['max']) }}">
                            <span class="range__min">{{ $deg($day['min']) }}</span>
                            <span class="range__track">
                                <span class="range__bar" style="left: {{ $pos($day['min']) }}%; right: {{ 100 - $pos($day['max']) }}%"></span>
                            </span>
                            <span class="range__max">{{ $deg($day['max']) }}</span>
                        </div>
                        <ul class="day__extra">
                            @if ($day['precipitation'] !== null) <li title="Вероятность осадков">Осадки {{ $day['precipitation'] }}%</li> @endif
                            @if ($day['wind'] !== null) <li title="Максимальный ветер">Ветер {{ $day['wind'] }} м/с</li> @endif
                            @if ($day['uv'] !== null) <li title="Максимальный УФ-индекс">УФ {{ $day['uv'] }}</li> @endif
                        </ul>
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
        <nav class="recent" aria-labelledby="recent-title">
            <h2 id="recent-title" class="section-title">Недавно искали</h2>
            <p class="chips">
                @foreach ($recent as $item)
                    <a class="chip" href="{{ route('weather.index', ['city' => $item->city] + $keep) }}"
                       @if ($city && $item->city === $city['name']) aria-current="page" @endif>{{ $item->city }}</a>
                @endforeach
            </p>
        </nav>
    @endif

    <nav class="themes" aria-labelledby="themes-title">
        <h2 id="themes-title" class="section-title">Оформление</h2>
        <p class="muted small">
            Подбирается само: по осадкам, затем по температуре (жара от +{{ Theme::HEAT_FROM }}°, мороз от {{ Theme::FROST_TO }}° и ниже), затем по сезону.
            Сейчас по погоде: {{ Theme::LABELS[$autoTheme] }}.
        </p>
        <p class="chips">
            <a class="chip" href="{{ route('weather.index', array_filter(['city' => request('city')])) }}" @unless ($themeForced) aria-current="page" @endunless>Авто</a>
            @foreach (Theme::LABELS as $key => $label)
                <a class="chip" href="{{ route('weather.index', array_filter(['city' => request('city'), 'theme' => $key])) }}"
                   @if ($themeForced && $theme === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </p>
    </nav>

    <footer class="foot muted small">
        ПМ01, работа с публичным API. Данные: <a href="https://open-meteo.com/">Open-Meteo</a> (без ключа, CC BY 4.0).
        Laravel {{ app()->version() }}, PHP {{ PHP_VERSION }}.
    </footer>
</main>

<script>
    // Пока ждём ответ сервера, кнопка показывает, что поиск идёт.
    document.querySelector('[data-search]').addEventListener('submit', (e) => {
        const btn = e.target.querySelector('button');
        btn.disabled = true;
        btn.querySelector('span').textContent = 'Ищу…';
    });
    // Блик на стекле следует за курсором.
    if (matchMedia('(hover: hover) and (prefers-reduced-motion: no-preference)').matches) {
        document.addEventListener('pointermove', (e) => {
            for (const el of document.querySelectorAll('.glass, .btn')) {
                const r = el.getBoundingClientRect();
                el.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                el.style.setProperty('--my', (e.clientY - r.top) + 'px');
            }
        }, { passive: true });
    }
</script>
</body>
</html>
