@php
    // Декоративный слой поверх фона. Позиции случайные, но одинаковые при каждой загрузке.
    mt_srand(crc32($theme));
    $r = fn (float $a, float $b, int $p = 1) => round($a + mt_rand() / mt_getrandmax() * ($b - $a), $p);
    $leafColors = ['#E8732C', '#C7461F', '#F2B33D', '#9C3D12', '#D99A2B'];
@endphp
<div class="sky" aria-hidden="true">
    <span class="sky__blob sky__blob--a"></span>
    <span class="sky__blob sky__blob--b"></span>
    <span class="sky__blob sky__blob--c"></span>
</div>

{{-- Задний слой: за панелями. --}}
<div class="fx" aria-hidden="true">
    @if ($ui === 'resort')
        {{-- Курорт: закатное солнце в полоску и силуэты пальм. Нарисовано с нуля, без материалов игры. --}}
        <div class="resort-sun"></div>
        <svg class="palms" viewBox="0 0 1200 300" preserveAspectRatio="xMidYMax slice">
            <g class="palm" transform="translate(90 300)"><path d="M0 0C6-70 10-140 34-210" fill="none" stroke-width="12"/><path d="M34-210c-40-10-80 0-110 30 36-10 70-16 104-10zM34-210c30-36 80-46 120-30-40 2-80 14-112 40zM34-210c10-46 50-76 96-80-34 20-60 50-86 88zM34-210c-36-36-86-40-124-20 40-2 82 6 116 26zM34-210c40 4 76 30 92 70-30-26-62-44-96-56z"/></g>
            <g class="palm" transform="translate(1050 300) scale(-1.25 1.25)"><path d="M0 0C6-70 10-140 34-210" fill="none" stroke-width="12"/><path d="M34-210c-40-10-80 0-110 30 36-10 70-16 104-10zM34-210c30-36 80-46 120-30-40 2-80 14-112 40zM34-210c10-46 50-76 96-80-34 20-60 50-86 88zM34-210c-36-36-86-40-124-20 40-2 82 6 116 26zM34-210c40 4 76 30 92 70-30-26-62-44-96-56z"/></g>
            <g class="palm" transform="translate(1160 300) scale(-.8 .8)"><path d="M0 0C6-70 10-140 34-210" fill="none" stroke-width="12"/><path d="M34-210c-40-10-80 0-110 30 36-10 70-16 104-10zM34-210c30-36 80-46 120-30-40 2-80 14-112 40zM34-210c10-46 50-76 96-80-34 20-60 50-86 88zM34-210c-36-36-86-40-124-20 40-2 82 6 116 26zM34-210c40 4 76 30 92 70-30-26-62-44-96-56z"/></g>
        </svg>
    @endif
    @if ($ui === 'terminal') <div class="scanlines"></div> @endif
    @if ($isNight && in_array($theme, ['summer', 'spring', 'autumn', 'winter', 'heat', 'frost'], true))
        @for ($i = 0; $i < 60; $i++)
            <i class="star" style="left: {{ $r(0, 100) }}%; top: {{ $r(0, 60) }}%; --s: {{ $r(1, 2.6) }}px; --d: {{ $r(2, 6) }}s; animation-delay: -{{ $r(0, 6) }}s"></i>
        @endfor
    @endif
    @switch($theme)
        @case('rain') @case('storm') <div class="streaks"></div> @break
        @case('autumn')
            @for ($i = 0; $i < 6; $i++)
                <i class="gust" style="top: {{ $r(5, 90) }}%; --w: {{ $r(80, 240, 0) }}px; --d: {{ $r(3, 7) }}s; animation-delay: -{{ $r(0, 7) }}s"></i>
            @endfor
            @for ($i = 0; $i < 12; $i++)
                <i class="leaf" style="left: {{ $r(-10, 95) }}%; --s: {{ $r(14, 24, 0) }}px; --c: {{ $leafColors[$i % 5] }}; --d: {{ $r(9, 17) }}s; --rot: {{ $r(-180, 180, 0) }}deg; animation-delay: -{{ $r(0, 17) }}s"></i>
            @endfor
            @break
        @case('snow') @case('winter')
            @for ($i = 0; $i < ($theme === 'snow' ? 50 : 16); $i++)
                <i class="flake" style="left: {{ $r(0, 100) }}%; --s: {{ $r(3, 6) }}px; --d: {{ $r(12, 22) }}s; --x: {{ $r(-60, 60, 0) }}px; animation-delay: -{{ $r(0, 22) }}s"></i>
            @endfor
            @break
        @case('summer')
            <div class="flare"></div>
            @for ($i = 0; $i < 14; $i++)
                <i class="mote" style="left: {{ $r(0, 100) }}%; top: {{ $r(10, 95) }}%; --d: {{ $r(6, 12) }}s; animation-delay: -{{ $r(0, 12) }}s"></i>
            @endfor
            @break
        @case('heat') <div class="flare flare--heat"></div> @break
        @case('fog')
            <div class="fogbank fogbank--a"></div>
            <div class="fogbank fogbank--b"></div>
            <div class="fogbank fogbank--c"></div>
            @break
    @endswitch
</div>

{{-- Передний слой: идёт прямо по интерфейсу, но не мешает кликать (pointer-events: none). --}}
<div class="fx fx--front" aria-hidden="true">
    @switch($theme)
        @case('rain')
        @case('storm')
            {{-- Капли, прилипшие к стеклу, и капли, которые стекают вниз со следом. --}}
            @for ($i = 0; $i < 46; $i++)
                <i class="drop" style="left: {{ $r(0, 100) }}%; top: {{ $r(0, 100) }}%; --s: {{ $r(4, 13) }}px"></i>
            @endfor
            @for ($i = 0; $i < 11; $i++)
                <i class="runner" style="left: {{ $r(2, 98) }}%; --s: {{ $r(7, 13) }}px; --d: {{ $r(5, 11) }}s; animation-delay: -{{ $r(0, 11) }}s"></i>
            @endfor
            @if ($theme === 'storm') <div class="flash"></div> @endif
            @break
        @case('snow')
        @case('winter')
            @for ($i = 0; $i < ($theme === 'snow' ? 24 : 10); $i++)
                <i class="flake" style="left: {{ $r(0, 100) }}%; --s: {{ $r(5, 9) }}px; --d: {{ $r(8, 15) }}s; --x: {{ $r(-60, 60, 0) }}px; animation-delay: -{{ $r(0, 15) }}s"></i>
            @endfor
            <div class="frost-corners"></div>
            @break
        @case('frost')
            <div class="frost-corners"></div>
            @for ($i = 0; $i < 18; $i++)
                <i class="sparkle" style="left: {{ $r(0, 100) }}%; top: {{ $r(0, 100) }}%; --d: {{ $r(2, 5) }}s; animation-delay: -{{ $r(0, 5) }}s"></i>
            @endfor
            @break
        @case('autumn')
            @for ($i = 0; $i < 8; $i++)
                <i class="leaf" style="left: {{ $r(-10, 95) }}%; --s: {{ $r(22, 34, 0) }}px; --c: {{ $leafColors[$i % 5] }}; --d: {{ $r(8, 14) }}s; --rot: {{ $r(-180, 180, 0) }}deg; animation-delay: -{{ $r(0, 14) }}s"></i>
            @endfor
            @break
        @case('spring')
            @for ($i = 0; $i < 14; $i++)
                <i class="petal" style="left: {{ $r(0, 100) }}%; --s: {{ $r(8, 14) }}px; --d: {{ $r(10, 18) }}s; --x: {{ $r(40, 160, 0) }}px; animation-delay: -{{ $r(0, 18) }}s"></i>
            @endfor
            @break
    @endswitch
</div>

{{-- Фильтры: «липкая жидкость» для капель жары и марево для фона. --}}
<svg width="0" height="0" style="position: absolute" aria-hidden="true">
    {{-- Заливки для значков: штриховка (Альманах) и неоновые градиенты (Курорт). --}}
    <defs>
        <pattern id="hatch" width="3" height="3" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path d="M0 0v3" stroke="currentColor" stroke-width="1.2"/></pattern>
        <pattern id="hatch-light" width="4" height="4" patternUnits="userSpaceOnUse" patternTransform="rotate(-45)"><path d="M0 0v4" stroke="currentColor" stroke-width=".5"/></pattern>
        <linearGradient id="g-sun" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFE14D"/><stop offset=".55" stop-color="#FF8A3D"/><stop offset="1" stop-color="#FF2E88"/></linearGradient>
        <linearGradient id="g-cloud" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#C9F3FF"/></linearGradient>
    </defs>
    <filter id="goo">
        <feGaussianBlur in="SourceGraphic" stdDeviation="5" result="b"/>
        <feColorMatrix in="b" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -8"/>
    </filter>
    <filter id="haze">
        <feTurbulence type="fractalNoise" baseFrequency="0.008 0.02" numOctaves="2" seed="3">
            <animate attributeName="baseFrequency" dur="9s" values="0.008 0.02;0.011 0.03;0.008 0.02" repeatCount="indefinite"/>
        </feTurbulence>
        <feDisplacementMap in="SourceGraphic" scale="38"/>
    </filter>
</svg>
