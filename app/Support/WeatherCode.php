<?php

namespace App\Support;

/**
 * Коды погоды WMO, которые возвращает Open-Meteo, на русском.
 * https://open-meteo.com/en/docs (раздел «WMO Weather interpretation codes»).
 */
class WeatherCode
{
    private const LABELS = [
        0 => 'Ясно',
        1 => 'Преимущественно ясно',
        2 => 'Переменная облачность',
        3 => 'Пасмурно',
        45 => 'Туман',
        48 => 'Изморозь',
        51 => 'Слабая морось',
        53 => 'Морось',
        55 => 'Сильная морось',
        56 => 'Ледяная морось',
        57 => 'Сильная ледяная морось',
        61 => 'Небольшой дождь',
        63 => 'Дождь',
        65 => 'Сильный дождь',
        66 => 'Ледяной дождь',
        67 => 'Сильный ледяной дождь',
        71 => 'Небольшой снег',
        73 => 'Снег',
        75 => 'Сильный снег',
        77 => 'Снежная крупа',
        80 => 'Небольшой ливень',
        81 => 'Ливень',
        82 => 'Сильный ливень',
        85 => 'Снегопад',
        86 => 'Сильный снегопад',
        95 => 'Гроза',
        96 => 'Гроза с градом',
        99 => 'Сильная гроза с градом',
    ];

    public static function label(?int $code): string
    {
        return self::LABELS[$code] ?? 'Нет данных';
    }

    /** Группа для цвета и значка: clear, partly, cloud, fog, rain, snow, storm. */
    public static function group(?int $code): string
    {
        return match (true) {
            $code === null => 'cloud',
            $code <= 1 => 'clear',
            $code === 2 => 'partly',
            $code === 3 => 'cloud',
            $code <= 48 => 'fog',
            in_array($code, [71, 73, 75, 77, 85, 86], true) => 'snow',
            $code >= 95 => 'storm',
            default => 'rain',
        };
    }
}
