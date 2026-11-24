{{-- Весна: ветка с листьями и цветами, обвивает угол панели. Рисуется один раз при загрузке. --}}
<svg {{ $attributes }} viewBox="0 0 240 200" aria-hidden="true">
    <path class="vine__stem" d="M4 196C10 150 6 120 28 96S70 70 74 44 104 6 150 10s70 4 86 -4"/>
    <path class="vine__stem vine__stem--thin" d="M28 96c-14-6-22-20-18-34M74 44c10 8 26 8 34 0M150 10c4 12 16 20 30 18"/>
    <g class="vine__leaves">
        <ellipse cx="14" cy="140" rx="11" ry="5" transform="rotate(-60 14 140)"/>
        <ellipse cx="40" cy="88" rx="12" ry="5" transform="rotate(-20 40 88)"/>
        <ellipse cx="92" cy="30" rx="12" ry="5" transform="rotate(-35 92 30)"/>
        <ellipse cx="128" cy="6" rx="10" ry="4.5" transform="rotate(10 128 6)"/>
        <ellipse cx="200" cy="12" rx="11" ry="4.5" transform="rotate(-8 200 12)"/>
    </g>
    @foreach ([[10, 62, 1], [108, 44, .85], [180, 28, 1.1], [58, 66, .7], [228, 4, .8]] as $i => [$x, $y, $s])
        <g class="vine__flower" transform="translate({{ $x }} {{ $y }}) scale({{ $s }})" style="--i: {{ $i }}">
            @for ($p = 0; $p < 5; $p++)
                <ellipse cx="0" cy="-7" rx="5" ry="7.5" transform="rotate({{ $p * 72 }})"/>
            @endfor
            <circle r="3.6" class="vine__heart"/>
        </g>
    @endforeach
</svg>
