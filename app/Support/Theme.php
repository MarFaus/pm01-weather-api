<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Оформление страницы под погоду: жара плавит интерфейс, дождь стекает каплями,
 * мороз покрывает кнопки инеем, весной всё в цветах, осенью летят листья.
 */
class Theme
{
    public const LABELS = [
        'heat' => 'Жара',
        'summer' => 'Лето',
        'spring' => 'Весна',
        'autumn' => 'Осень',
        'winter' => 'Зима',
        'frost' => 'Мороз',
        'rain' => 'Дождь',
        'storm' => 'Гроза',
        'snow' => 'Снег',
        'fog' => 'Туман',
    ];

    public const HEAT_FROM = 25;
    public const FROST_TO = 0;

    /**
     * Сначала то, что видно за окном (осадки, туман), потом температура, потом сезон.
     *
     * @param  array  $current  текущая погода из OpenMeteo::forecast()
     */
    public static function resolve(array $current, float $latitude): string
    {
        $byWeather = match ($current['group']) {
            'storm' => 'storm',
            'snow' => 'snow',
            'rain' => 'rain',
            'fog' => 'fog',
            default => null,
        };

        return $byWeather ?? match (true) {
            $current['temperature'] >= self::HEAT_FROM => 'heat',
            $current['temperature'] <= self::FROST_TO => 'frost',
            default => self::season(Carbon::parse($current['time']), $latitude),
        };
    }

    /** Сезон по местной дате. В южном полушарии времена года наоборот. */
    public static function season(Carbon $date, float $latitude): string
    {
        $month = $date->month;
        if ($latitude < 0) {
            $month = ($month + 5) % 12 + 1;
        }

        return match (true) {
            $month >= 3 && $month <= 5 => 'spring',
            $month >= 6 && $month <= 8 => 'summer',
            $month >= 9 && $month <= 11 => 'autumn',
            default => 'winter',
        };
    }
}
