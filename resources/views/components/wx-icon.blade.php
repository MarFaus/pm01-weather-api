@props(['group' => 'cloud', 'day' => true, 'size' => 24])
{{--
    Значок погоды или подробности (d-wind, d-uv, d-eye, d-drop, d-gauge, d-sun, d-gear).
    Рисуется по-разному в каждом стиле интерфейса ($ui общий для всех шаблонов):
    terminal — текст, pixel — пиксель-арт, остальные — векторные линии со своей толщиной и заливкой.
--}}
@php
    $ui = $ui ?? 'glass';
    if ($group === 'clear' && ! $day) { $group = 'night'; }
    if ($group === 'partly' && ! $day) { $group = 'partly-night'; }
@endphp

@if ($ui === 'terminal')
    @php
        $ascii = [
            'clear' => '\o/', 'night' => '(C ', 'partly' => '\o~', 'partly-night' => '(C~', 'cloud' => '(~)',
            'fog' => '≡≡≡', 'rain' => '///', 'snow' => '*.*', 'storm' => '/!\\',
            'd-wind' => '~>', 'd-uv' => 'UV', 'd-eye' => '<o>', 'd-drop' => '%', 'd-gauge' => '@', 'd-sun' => '^v', 'd-gear' => '[*]',
        ];
    @endphp
    <span {{ $attributes->merge(['class' => 'wx wx--ascii']) }} aria-hidden="true">{{ $ascii[$group] ?? '(~)' }}</span>

@elseif ($ui === 'pixel' && ! str_starts_with($group, 'd-'))
    @php
        // Пиксель-арт 12×12: каждая буква — цвет клетки, точка — пусто.
        $cloudTop = ['....KKKK....', '...KWWWWK...', '..KWWWWWWKK.', '.KWWWWWWWWWK', 'KWWWWWWWWWWK', '.KKKKKKKKKK.'];
        $art = [
            'clear' => ['....Y..Y....', '............', 'Y...OOOO...Y', '...OYYYYO...', '..OYYYYYYO..', '..OYYYYYYO..', '..OYYYYYYO..', '..OYYYYYYO..', '...OYYYYO...', 'Y...OOOO...Y', '............', '....Y..Y....'],
            'night' => ['............', '....MMM.....', '...MMM......', '..MMM.......', '..MMM.....S.', '..MMM.......', '..MMMM....M.', '...MMMMMMMM.', '....MMMMMM..', '............', '.S..........', '............'],
            'cloud' => ['............', '............', '....KKKK....', '...KWWWWK...', '..KWWWWWWKK.', '.KWWWWWWWWWK', 'KWWWWWWWWWWK', 'KWWWWWWWWWWK', '.KKKKKKKKKK.', '............', '............', '............'],
            'partly' => ['.Y..........', '..OOO.......', '.OYYYO......', '.OYYKKKK....', '.OYKWWWWK...', '..KWWWWWWKK.', '.KWWWWWWWWWK', 'KWWWWWWWWWWK', 'KWWWWWWWWWWK', '.KKKKKKKKKK.', '............', '............'],
            'partly-night' => ['..MM........', '.MM.........', '.MM.........', '.MMMKKKK....', '..MKWWWWK...', '..KWWWWWWKK.', '.KWWWWWWWWWK', 'KWWWWWWWWWWK', 'KWWWWWWWWWWK', '.KKKKKKKKKK.', '............', '............'],
            'fog' => ['............', '............', 'GGGGGGGGG...', '............', '...GGGGGGGGG', '............', 'GGGGGGGGGG..', '............', '..GGGGGGGGGG', '............', 'GGGGGGG.....', '............'],
            'rain' => array_merge($cloudTop, ['............', '..B...B...B.', '.B...B...B..', '............', '..B...B...B.', '.B...B...B..']),
            'snow' => array_merge($cloudTop, ['............', '..S...S...S.', '.SSS.SSS.SSS', '..S...S...S.', '............', '....S...S...']),
            'storm' => array_merge($cloudTop, ['......YY....', '.....YY.....', '....YYYY....', '......YY....', '.....YY.....', '....Y.......']),
        ];
        $rows = $art[$group] ?? $art['cloud'];
        $colors = ['Y' => '#FFD23F', 'O' => '#FF9F1C', 'K' => '#1B1B2F', 'W' => '#FFFFFF', 'B' => '#3FA9F5', 'S' => '#DDEEFF', 'G' => '#C9D3DD', 'M' => '#F4E9B0'];
    @endphp
    <svg {{ $attributes->merge(['class' => 'wx wx--pixel']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 12 12" shape-rendering="crispEdges" aria-hidden="true">
        @foreach ($rows as $y => $row)
            @foreach (str_split($row) as $x => $c)
                @if ($c !== '.') <rect x="{{ $x }}" y="{{ $y }}" width="1" height="1" fill="{{ $colors[$c] }}"/> @endif
            @endforeach
        @endforeach
    </svg>

@else
    @php
        // Векторные стили отличаются толщиной линии, цветом обводки и заливками.
        $look = match ($ui) {
            'almanac' => ['sw' => 1.3, 'stroke' => 'currentColor', 'sun' => 'url(#hatch)', 'edge' => 'currentColor', 'cloud' => 'url(#hatch-light)', 'wet' => 'currentColor', 'bolt' => 'currentColor', 'moon' => 'url(#hatch)'],
            'brutal' => ['sw' => 2.4, 'stroke' => '#111', 'sun' => '#FFD400', 'edge' => '#111', 'cloud' => '#FFF', 'wet' => '#2F6BFF', 'bolt' => '#FF3B30', 'moon' => '#FFE680'],
            'resort' => ['sw' => 1.8, 'stroke' => '#FF4FA3', 'sun' => 'url(#g-sun)', 'edge' => '#FF4FA3', 'cloud' => 'url(#g-cloud)', 'wet' => '#2FE6E6', 'bolt' => '#FFE14D', 'moon' => 'url(#g-sun)'],
            'pixel' => ['sw' => 2, 'stroke' => 'currentColor', 'sun' => 'none', 'edge' => 'currentColor', 'cloud' => 'none', 'wet' => 'currentColor', 'bolt' => 'currentColor', 'moon' => 'none'],
            default => ['sw' => 1.6, 'stroke' => 'currentColor', 'sun' => 'var(--sun)', 'edge' => 'var(--sun-edge)', 'cloud' => 'var(--panel)', 'wet' => 'var(--cold)', 'bolt' => 'var(--warm)', 'moon' => 'var(--moon)'],
        };
        $cloud = 'M7.5 19h9a4 4 0 0 0 .6-7.96 5.5 5.5 0 0 0-10.6 1.06A3.5 3.5 0 0 0 7.5 19z';
        $cloudLow = 'M7.5 15.5h9a3.6 3.6 0 0 0 .5-7.15 5 5 0 0 0-9.6.95 3.2 3.2 0 0 0 .1 6.2z';
    @endphp
    <svg {{ $attributes->merge(['class' => 'wx']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
         fill="none" stroke="{{ $look['stroke'] }}" stroke-width="{{ $look['sw'] }}" stroke-linecap="{{ $ui === 'pixel' ? 'square' : 'round' }}" stroke-linejoin="{{ $ui === 'pixel' ? 'miter' : 'round' }}"
         @if ($ui === 'pixel') shape-rendering="crispEdges" @endif aria-hidden="true">
        @switch($group)
            @case('clear')
                <circle cx="12" cy="12" r="4.2" fill="{{ $look['sun'] }}" stroke="{{ $look['edge'] }}"/>
                <path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4" stroke="{{ $look['edge'] }}"/>
                @break
            @case('night')
                <path d="M19 14.5A7.5 7.5 0 0 1 9.5 5a7.5 7.5 0 1 0 9.5 9.5z" fill="{{ $look['moon'] }}"/>
                @break
            @case('partly')
                <circle cx="8.5" cy="8" r="3.2" fill="{{ $look['sun'] }}" stroke="{{ $look['edge'] }}"/>
                <path d="M8.5 2.3v1.2M2.8 8h1.2M4.5 4l.8.8M12.5 4l-.8.8" stroke="{{ $look['edge'] }}"/>
                <path d="{{ $cloud }}" fill="{{ $look['cloud'] }}"/>
                @break
            @case('partly-night')
                <path d="M12 9.6A4.6 4.6 0 0 1 6.4 4a4.6 4.6 0 1 0 5.6 5.6z" fill="{{ $look['moon'] }}"/>
                <path d="{{ $cloud }}" fill="{{ $look['cloud'] }}"/>
                @break
            @case('fog')
                <path d="{{ $cloudLow }}" fill="{{ $look['cloud'] }}"/>
                <path d="M4 18.5h11M8 21.5h12"/>
                @break
            @case('rain')
                <path d="{{ $cloudLow }}" fill="{{ $look['cloud'] }}"/>
                <path d="M8.5 18l-1 2.5M12.5 18l-1 2.5M16.5 18l-1 2.5" stroke="{{ $look['wet'] }}"/>
                @break
            @case('snow')
                <path d="{{ $cloudLow }}" fill="{{ $look['cloud'] }}"/>
                <path d="M8.5 18.5v2.5M7.3 19.75h2.4M15.5 18.5v2.5M14.3 19.75h2.4M12 20.5v2M11 21.5h2" stroke="{{ $look['wet'] }}"/>
                @break
            @case('storm')
                <path d="{{ $cloudLow }}" fill="{{ $look['cloud'] }}"/>
                <path d="M12.5 16.5l-2 3.5h3l-2 3.5" stroke="{{ $look['bolt'] }}"/>
                @break
            {{-- Подробности --}}
            @case('d-wind')
                <path d="M3 8h10a3 3 0 1 0-3-3M3 12h15a3 3 0 1 1-3 3M3 16h7"/>
                @break
            @case('d-uv')
                <circle cx="12" cy="12" r="3.5" fill="{{ $look['sun'] }}" stroke="{{ $look['edge'] }}"/>
                <path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8" stroke="{{ $look['edge'] }}"/>
                @break
            @case('d-eye')
                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/>
                <circle cx="12" cy="12" r="3" fill="{{ $look['wet'] }}"/>
                @break
            @case('d-drop')
                <path d="M12 3s6.5 7 6.5 11.5a6.5 6.5 0 0 1-13 0C5.5 10 12 3 12 3z" fill="{{ $look['wet'] }}" fill-opacity=".35"/>
                @break
            @case('d-gauge')
                <path d="M4 17a8 8 0 1 1 16 0"/>
                <path d="M12 17l4-5" stroke="{{ $look['bolt'] }}"/>
                <circle cx="12" cy="17" r="1.4" fill="{{ $look['stroke'] }}"/>
                @break
            @case('d-sun')
                <path d="M3 18h18M7 18a5 5 0 0 1 10 0" fill="{{ $look['sun'] }}"/>
                <path d="M12 6v3M5.5 9.5l2 2M18.5 9.5l-2 2M9 21h6" stroke="{{ $look['edge'] }}"/>
                @break
            @case('d-gear')
                <circle cx="12" cy="12" r="3"/>
                <path d="M12 2.5v3M12 18.5v3M21.5 12h-3M5.5 12h-3M18.7 5.3l-2.1 2.1M7.4 16.6l-2.1 2.1M18.7 18.7l-2.1-2.1M7.4 7.4 5.3 5.3"/>
                <circle cx="12" cy="12" r="6.5"/>
                @break
            @default
                <path d="{{ $cloud }}" fill="{{ $look['cloud'] }}"/>
        @endswitch
    </svg>
@endif
