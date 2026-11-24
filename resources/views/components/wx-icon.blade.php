@props(['group' => 'cloud', 'day' => true, 'size' => 24])
{{-- Значок погоды. Линии рисуются currentColor, солнце залито акцентом --sun. --}}
@php
    $cloud = 'M7.5 19h9a4 4 0 0 0 .6-7.96 5.5 5.5 0 0 0-10.6 1.06A3.5 3.5 0 0 0 7.5 19z';
    $cloudLow = 'M7.5 15.5h9a3.6 3.6 0 0 0 .5-7.15 5 5 0 0 0-9.6.95 3.2 3.2 0 0 0 .1 6.2z';
@endphp
<svg {{ $attributes->merge(['class' => 'wx']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($group)
        @case('clear')
            @if ($day)
                <circle cx="12" cy="12" r="4.2" fill="var(--sun)" stroke="var(--sun-edge)"/>
                <path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4" stroke="var(--sun-edge)"/>
            @else
                <path d="M19 14.5A7.5 7.5 0 0 1 9.5 5a7.5 7.5 0 1 0 9.5 9.5z" fill="var(--moon)"/>
            @endif
            @break
        @case('partly')
            @if ($day)
                <circle cx="8.5" cy="8" r="3.2" fill="var(--sun)" stroke="var(--sun-edge)"/>
                <path d="M8.5 2.3v1.2M2.8 8h1.2M4.5 4l.8.8M12.5 4l-.8.8" stroke="var(--sun-edge)"/>
            @else
                <path d="M12 9.6A4.6 4.6 0 0 1 6.4 4a4.6 4.6 0 1 0 5.6 5.6z" fill="var(--moon)"/>
            @endif
            <path d="{{ $cloud }}" fill="var(--panel)"/>
            @break
        @case('fog')
            <path d="{{ $cloudLow }}" fill="var(--panel)"/>
            <path d="M4 18.5h11M8 21.5h12"/>
            @break
        @case('rain')
            <path d="{{ $cloudLow }}" fill="var(--panel)"/>
            <path d="M8.5 18l-1 2.5M12.5 18l-1 2.5M16.5 18l-1 2.5" stroke="var(--cold)"/>
            @break
        @case('snow')
            <path d="{{ $cloudLow }}" fill="var(--panel)"/>
            <path d="M8.5 18.5v2.5M7.3 19.75h2.4M15.5 18.5v2.5M14.3 19.75h2.4M12 20.5v2M11 21.5h2" stroke="var(--cold)"/>
            @break
        @case('storm')
            <path d="{{ $cloudLow }}" fill="var(--panel)"/>
            <path d="M12.5 16.5l-2 3.5h3l-2 3.5" stroke="var(--warm)"/>
            @break
        @default
            <path d="{{ $cloud }}" fill="var(--panel)"/>
    @endswitch
</svg>
