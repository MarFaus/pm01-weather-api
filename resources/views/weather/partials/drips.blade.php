@php
    // Жара: нижний край панели «плавится» и стекает каплями.
    mt_srand($seed * 7919);
    $r = fn (float $a, float $b) => round($a + mt_rand() / mt_getrandmax() * ($b - $a), 1);
@endphp
<span class="drips" aria-hidden="true">
    @for ($i = 0; $i < 9; $i++)
        <i style="left: {{ $r(4, 94) }}%; --w: {{ $r(10, 22) }}px; --h: {{ $r(18, 54) }}px; --d: {{ $r(3, 7) }}s; animation-delay: -{{ $r(0, 7) }}s"></i>
    @endfor
</span>
