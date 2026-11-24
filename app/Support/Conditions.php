<?php

namespace App\Support;

/**
 * Переводит сырые числа погоды в понятные слова: «УФ 6» → «высокий, нужен крем».
 */
class Conditions
{
    private const COMPASS = ['северный', 'северо-восточный', 'восточный', 'юго-восточный', 'южный', 'юго-западный', 'западный', 'северо-западный'];
    private const COMPASS_SHORT = ['С', 'СВ', 'В', 'ЮВ', 'Ю', 'ЮЗ', 'З', 'СЗ'];

    /** УФ-индекс по шкале ВОЗ. level: low, moderate, high, very-high, extreme. */
    public static function uv(?float $index): array
    {
        return match (true) {
            $index === null => ['level' => 'none', 'label' => 'нет данных', 'advice' => ''],
            $index < 3 => ['level' => 'low', 'label' => 'низкий', 'advice' => 'Защита не нужна'],
            $index < 6 => ['level' => 'moderate', 'label' => 'умеренный', 'advice' => 'Днём лучше очки и крем SPF 30'],
            $index < 8 => ['level' => 'high', 'label' => 'высокий', 'advice' => 'Нужны крем, очки и головной убор'],
            $index < 11 => ['level' => 'very-high', 'label' => 'очень высокий', 'advice' => 'С 11 до 16 лучше быть в тени'],
            default => ['level' => 'extreme', 'label' => 'экстремальный', 'advice' => 'Без защиты на солнце не выходить'],
        };
    }

    /** Видимость в километрах. */
    public static function visibility(?float $km): string
    {
        return match (true) {
            $km === null => 'нет данных',
            $km < 1 => 'очень плохая, туман',
            $km < 4 => 'плохая, дымка',
            $km < 10 => 'средняя',
            $km < 20 => 'хорошая',
            default => 'отличная',
        };
    }

    /** Сила ветра в м/с по шкале Бофорта. */
    public static function wind(?float $speed): string
    {
        return match (true) {
            $speed === null => 'нет данных',
            $speed < 0.3 => 'штиль',
            $speed < 1.6 => 'тихий',
            $speed < 3.4 => 'лёгкий',
            $speed < 5.5 => 'слабый',
            $speed < 8 => 'умеренный',
            $speed < 10.8 => 'свежий',
            $speed < 13.9 => 'сильный',
            $speed < 17.2 => 'крепкий',
            $speed < 20.8 => 'очень крепкий',
            default => 'шторм',
        };
    }

    /** Направление, откуда дует ветер: 90° → «восточный», short → «В». */
    public static function direction(?float $degrees, bool $short = false): string
    {
        if ($degrees === null) {
            return '';
        }
        $i = (int) round(fmod($degrees + 360, 360) / 45) % 8;

        return $short ? self::COMPASS_SHORT[$i] : self::COMPASS[$i];
    }
}
