@php
    use App\Support\Conditions;
    use App\Support\Style;
    use App\Support\Theme;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $days = $forecast['days'] ?? [];
    $hours = $forecast['hours'] ?? [];
    $deg = fn (int $t) => ($t > 0 ? '+' : '').$t.'°';
    $term = $ui === 'terminal';

    // Неделя: все полоски на одной шкале, поэтому видно, какой день теплее.
    $lo = $days ? min(array_column($days, 'min')) : 0;
    $hi = $days ? max(array_column($days, 'max')) : 1;
    $span = max(1, $hi - $lo);
    $pos = fn (int $t) => round(($t - $lo) / $span * 100, 1);
    // Терминал рисует ту же полоску символами: ░ вне диапазона, █ внутри.
    $ascii = function (int $min, int $max) use ($lo, $span) {
        $a = (int) round(($min - $lo) / $span * 20);
        $b = max($a + 1, (int) round(($max - $lo) / $span * 20));

        return str_repeat('░', $a).str_repeat('█', $b - $a).str_repeat('░', 20 - $b);
    };

    // 24 часа: линия температуры поверх колонок шириной $col пикселей. В пиксельном стиле она ступенчатая.
    $col = 64;
    $hLo = $hours ? min(array_column($hours, 'temperature')) : 0;
    $hHi = $hours ? max(array_column($hours, 'temperature')) : 1;
    $hy = fn (int $t) => round(24 + (1 - ($t - $hLo) / max(1, $hHi - $hLo)) * 40, 1);
    $points = collect($hours)->flatMap(function ($h, $i) use ($col, $hy, $ui) {
        $x = $i * $col + $col / 2;
        $y = $hy($h['temperature']);

        return $ui === 'pixel' ? [($x - $col / 2).','.$y, ($x + $col / 2).','.$y] : ["{$x},{$y}"];
    })->implode(' ');

    // Ссылки сохраняют город и выбранную вручную погодную тему.
    $keep = $themeForced ? ['theme' => $theme] : [];
    $here = fn (array $extra = []) => route('weather.index', array_filter(['city' => request('city')]) + $keep + $extra);
    $isNight = $night && ! in_array($theme, ['rain', 'storm'], true);

    // Заголовок блока: в терминале это команда, в остальных стилях обычный текст.
    $title = fn (string $text, string $cmd) => $term ? '$ '.$cmd : $text;
    $allFonts = 'https://fonts.googleapis.com/css2?'.collect(Style::ALL)->pluck('fonts')->implode('&').'&display=swap';
@endphp
<!doctype html>
<html lang="ru" class="style-{{ $ui }} theme-{{ $theme }} {{ $isNight ? 'is-night' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $city ? 'Погода: '.$city['name'] : 'Погода' }} — Сводка</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Все шрифты одним файлом: браузер скачает только те, что реально видны (превью стилей в настройках). --}}
    <link href="{{ $allFonts }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ filemtime(public_path('css/styles.css')) }}">
</head>
<body style="--wind: {{ min(12, $forecast['current']['wind'] ?? 2) }}">
@include('weather.partials.fx')

<main class="page">
    <header class="head">
        <a class="brand" href="{{ route('weather.index', $keep) }}">{{ $term ? 'svodka@weather:~' : 'Сводка' }}</a>
        <form class="search" method="get" action="{{ route('weather.index') }}" role="search" data-search>
            <label class="visually-hidden" for="city">Город</label>
            @if ($term) <span class="search__prompt" aria-hidden="true">$ weather --city</span> @endif
            <input id="city" name="city" type="search" value="{{ request('city') }}" placeholder="Город, например Алматы"
                   maxlength="80" autocomplete="off" @error('city') aria-invalid="true" aria-describedby="city-error" @enderror>
            @if ($themeForced) <input type="hidden" name="theme" value="{{ $theme }}"> @endif
            <button class="btn" type="submit"><span>{{ $term ? 'Enter' : 'Показать погоду' }}</span></button>
        </form>
        <button class="btn btn--ghost" type="button" data-open-settings aria-haspopup="dialog" aria-controls="settings">
            <x-wx-icon group="d-gear" :size="20"/><span>Настройки</span>
        </button>
    </header>
    @error('city') <p class="alert" id="city-error">{{ $message }}</p> @enderror
    @error('style') <p class="alert">Такого стиля нет, оставлен прежний.</p> @enderror

    @if ($error)
        <section class="panel empty" role="alert">
            <h1>Прогноз недоступен</h1>
            <p>{{ $error }}</p>
            <a class="btn btn--small" href="{{ request()->fullUrl() }}"><span>Попробовать снова</span></a>
        </section>
    @elseif (! $city)
        <section class="panel empty">
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
            $local = Carbon::parse($now['time'])->locale('ru');
            $place = collect([$city['region'] !== $city['name'] ? $city['region'] : null, $city['country']])->filter()->implode(', ');
            [$rise, $set] = $today && $today['sunrise'] ? [Carbon::parse($today['sunrise']), Carbon::parse($today['sunset'])] : [null, null];
            $daylight = $rise ? $rise->diffInMinutes($set) : null;
            // Доля от «максимума» для круговых приборов (Курорт) и шкал (Пиксели), от 0 до 100.
            $pct = fn (?float $v, float $max, float $min = 0) => $v === null ? 0 : (int) round(max(0, min(1, ($v - $min) / ($max - $min))) * 100);
        @endphp

        <div class="board">
            {{-- Сейчас --}}
            <section class="panel hero" aria-labelledby="city-name">
                @includeWhen($theme === 'heat' && ! $term, 'weather.partials.drips', ['seed' => 1])
                @if ($theme === 'spring' && in_array($ui, ['glass', 'almanac', 'resort'], true)) <x-vine class="vine vine--tl"/> <x-vine class="vine vine--br"/> @endif

                @if ($ui === 'almanac')
                    <p class="masthead">
                        <span>Погодный листок</span>
                        <span>{{ Str::ucfirst($local->isoFormat('dddd, D MMMM YYYY')) }}</span>
                        <span>Выпуск № {{ $local->dayOfYear }}</span>
                    </p>
                @endif
                @if ($term)
                    <p class="cmd">$ weather now --city="{{ $city['name'] }}"</p>
                @endif

                <div class="hero__main">
                    <div class="hero__place">
                        @if ($ui === 'resort') <p class="postcard">Привет из</p> @endif
                        <h1 id="city-name" class="hero__city">{{ $city['name'] }}</h1>
                        <p class="muted">{{ $place }}</p>
                        <p class="muted small">Местное время {{ $local->format('H:i') }}</p>
                    </div>
                    <div class="hero__now">
                        <x-wx-icon :group="$now['group']" :day="$now['is_day']" :size="88" class="hero__icon"/>
                        <p class="hero__temp" aria-label="Температура {{ $deg($now['temperature']) }}">{{ $deg($now['temperature']) }}</p>
                    </div>
                    <div class="hero__summary">
                        <p class="hero__label"><span>{{ $now['label'] }}</span></p>
                        <p class="muted">Ощущается как {{ $deg($now['feels_like']) }}</p>
                        @if ($today) <p class="muted">Днём до {{ $deg($today['max']) }}, ночью до {{ $deg($today['min']) }}</p> @endif
                    </div>
                </div>
            </section>

            {{-- Подробности --}}
            <section class="panel details-panel" aria-labelledby="details-title">
                <h2 id="details-title" class="section-title">{{ $title('Подробнее', 'weather details') }}</h2>
                <dl class="details">
                    <div class="tile" style="--p: {{ $pct($now['wind'], 20) }}">
                        <dt><x-wx-icon group="d-wind" :size="22"/> Ветер</dt>
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
                    <div class="tile" style="--p: {{ $pct($now['uv'], 11) }}">
                        <dt><x-wx-icon group="d-uv" :size="22"/> УФ-индекс</dt>
                        <dd class="tile__value">{{ $now['uv'] ?? '—' }} <small>{{ $uv['label'] }}</small></dd>
                        <dd class="uv" aria-hidden="true"><span style="left: {{ $pct($now['uv'], 11) }}%"></span></dd>
                        <dd class="tile__note">{{ $uv['advice'] }}@if ($today && $today['uv'] !== null). Максимум сегодня {{ $today['uv'] }}@endif</dd>
                    </div>
                    <div class="tile" style="--p: {{ $pct($now['visibility'], 30) }}">
                        <dt><x-wx-icon group="d-eye" :size="22"/> Видимость</dt>
                        <dd class="tile__value">{{ $now['visibility'] !== null ? ($now['visibility'] >= 10 ? round($now['visibility']) : $now['visibility']) : '—' }} <small>км</small></dd>
                        <dd class="tile__note">{{ Str::ucfirst(Conditions::visibility($now['visibility'])) }}</dd>
                    </div>
                    <div class="tile" style="--p: {{ $now['humidity'] }}">
                        <dt><x-wx-icon group="d-drop" :size="22"/> Влажность</dt>
                        <dd class="tile__value">{{ $now['humidity'] }}<small>%</small></dd>
                        <dd class="meter" aria-hidden="true"><span style="width: {{ $now['humidity'] }}%"></span></dd>
                    </div>
                    <div class="tile" style="--p: {{ $pct($now['pressure'], 790, 730) }}">
                        <dt><x-wx-icon group="d-gauge" :size="22"/> Давление</dt>
                        <dd class="tile__value">{{ $now['pressure'] ?? '—' }} <small>мм рт. ст.</small></dd>
                        @if ($now['pressure'])
                            <dd class="tile__note">{{ $now['pressure'] > 765 ? 'Выше нормы' : ($now['pressure'] < 755 ? 'Ниже нормы' : 'В норме') }} (норма 760)</dd>
                        @endif
                    </div>
                    @if ($rise)
                        <div class="tile" style="--p: {{ $pct($daylight, 1440) }}">
                            <dt><x-wx-icon group="d-sun" :size="22"/> Солнце</dt>
                            <dd class="tile__value">{{ $rise->format('H:i') }} <small>→</small> {{ $set->format('H:i') }}</dd>
                            <dd class="tile__note">Световой день {{ intdiv($daylight, 60) }} ч {{ $daylight % 60 }} мин</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- 24 часа --}}
            @if ($hours)
                <section class="panel hours" aria-labelledby="hours-title">
                    @includeWhen($theme === 'heat' && ! $term, 'weather.partials.drips', ['seed' => 2])
                    <h2 id="hours-title" class="section-title">{{ $title('Ближайшие 24 часа', 'weather hourly --next=24h') }}</h2>
                    <div class="hours__scroll" tabindex="0" aria-label="Почасовой прогноз, прокручивается вбок">
                        <div class="hours__track" style="width: {{ count($hours) * $col }}px">
                            <svg class="hours__curve" width="{{ count($hours) * $col }}" height="72" aria-hidden="true" @if ($ui === 'pixel') shape-rendering="crispEdges" @endif>
                                <polyline points="{{ $points }}"/>
                                @foreach ($hours as $i => $h)
                                    @php [$cx, $cy] = [$i * $col + $col / 2, $hy($h['temperature'])]; @endphp
                                    @if ($ui === 'pixel')
                                        <rect x="{{ $cx - 4 }}" y="{{ $cy - 4 }}" width="8" height="8"/>
                                    @else
                                        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="3"/>
                                    @endif
                                    <text x="{{ $cx }}" y="{{ $cy - 9 }}">{{ $deg($h['temperature']) }}</text>
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
            <section class="panel week" aria-labelledby="week-title">
                @includeWhen($theme === 'heat' && ! $term, 'weather.partials.drips', ['seed' => 3])
                @if ($theme === 'spring' && $ui === 'glass') <x-vine class="vine vine--tr"/> @endif
                <h2 id="week-title" class="section-title">{{ $title('7 дней', 'weather week') }}</h2>
                <ol class="days">
                    @foreach ($days as $i => $day)
                        @php $date = Carbon::parse($day['date'])->locale('ru'); @endphp
                        <li class="day">
                            <p class="day__name">{{ $i === 0 ? 'Сегодня' : mb_convert_case($date->isoFormat('dd'), MB_CASE_TITLE) }}
                                <span class="muted">{{ $date->isoFormat('D MMM') }}</span></p>
                            <p class="day__label"><x-wx-icon :group="$day['group']" :size="26"/> <span>{{ $day['label'] }}</span></p>
                            <div class="range" role="img" aria-label="от {{ $deg($day['min']) }} до {{ $deg($day['max']) }}">
                                <span class="range__min">{{ $deg($day['min']) }}</span>
                                @if ($term)
                                    <span class="range__ascii" aria-hidden="true">{{ $ascii($day['min'], $day['max']) }}</span>
                                @else
                                    <span class="range__track">
                                        <span class="range__bar" style="left: {{ $pos($day['min']) }}%; right: {{ 100 - $pos($day['max']) }}%"></span>
                                    </span>
                                @endif
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
        </div>
    @endif

    @if ($recent->isNotEmpty())
        <nav class="recent" aria-labelledby="recent-title">
            <h2 id="recent-title" class="section-title">{{ $title('Недавно искали', 'history') }}</h2>
            <p class="chips">
                @foreach ($recent as $item)
                    <a class="chip" href="{{ route('weather.index', ['city' => $item->city] + $keep) }}"
                       @if ($city && $item->city === $city['name']) aria-current="page" @endif>{{ $item->city }}</a>
                @endforeach
            </p>
        </nav>
    @endif

    <footer class="foot muted small">
        ПМ01, работа с публичным API. Данные: <a href="https://open-meteo.com/">Open-Meteo</a> (без ключа, CC BY 4.0).
        Laravel {{ app()->version() }}, PHP {{ PHP_VERSION }}.
        @if ($ui === 'resort') Стиль «Курорт» вдохновлён эстетикой трейлера GTA VI, это фанатский оммаж без материалов игры. @endif
    </footer>
</main>

{{-- Настройки: стиль интерфейса и оформление под погоду. --}}
<dialog class="settings" id="settings" aria-labelledby="settings-title">
    <div class="settings__head">
        <h2 id="settings-title">Настройки</h2>
        <form method="dialog"><button class="settings__close" aria-label="Закрыть настройки">✕</button></form>
    </div>

    <h3>Стиль интерфейса</h3>
    <p class="settings__hint">Меняет всё: раскладку, формы, шрифты и значки. Запоминается в этом браузере.</p>
    <ul class="styles">
        @foreach (Style::ALL as $key => $style)
            <li>
                <a class="style-card" href="{{ $here(['style' => $key]) }}" @if ($ui === $key) aria-current="true" @endif>
                    <span class="preview preview--{{ $key }}" aria-hidden="true">
                        <span class="preview__temp">+13°</span>
                        <span class="preview__bar"></span>
                        <span class="preview__bar preview__bar--short"></span>
                    </span>
                    <span class="style-card__name">{{ $style['label'] }}</span>
                    <span class="style-card__about">{{ $style['about'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <h3>Оформление под погоду</h3>
    <p class="settings__hint">
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
</dialog>

<script>
    // Пока ждём ответ сервера, кнопка показывает, что поиск идёт.
    document.querySelector('[data-search]').addEventListener('submit', (e) => {
        const btn = e.target.querySelector('button');
        btn.disabled = true;
        btn.querySelector('span').textContent = 'Ищу…';
    });
    // Окно настроек: открыть по кнопке, закрыть по Esc, крестику или клику мимо окна.
    const settings = document.getElementById('settings');
    document.querySelector('[data-open-settings]').addEventListener('click', () => settings.showModal());
    settings.addEventListener('click', (e) => { if (e.target === settings) settings.close(); });
    if (location.hash === '#settings') settings.showModal();
    // Блик на стекле следует за курсором.
    if (document.documentElement.classList.contains('style-glass') && matchMedia('(hover: hover) and (prefers-reduced-motion: no-preference)').matches) {
        document.addEventListener('pointermove', (e) => {
            for (const el of document.querySelectorAll('.panel, .btn')) {
                const r = el.getBoundingClientRect();
                el.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                el.style.setProperty('--my', (e.clientY - r.top) + 'px');
            }
        }, { passive: true });
    }
</script>
</body>
</html>
