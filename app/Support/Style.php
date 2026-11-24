<?php

namespace App\Support;

/**
 * Стиль интерфейса: формы, шрифты, иконки и материалы. Не путать с Theme (оформление под погоду):
 * погода задаёт цвета неба и эффекты, а стиль задаёт, из чего сделан интерфейс.
 */
class Style
{
    public const DEFAULT = 'glass';
    public const COOKIE = 'ui_style';

    public const ALL = [
        'glass' => [
            'label' => 'Стекло',
            'about' => 'Жидкое стекло, мягкие формы, тонкие линейные иконки.',
            'fonts' => 'family=Onest:wght@300;400;500;600;700',
        ],
        'almanac' => [
            'label' => 'Альманах',
            'about' => 'Бумага и типографская краска: антиква, линейки, штриховка.',
            'fonts' => 'family=Literata:ital,wght@0,400;0,600;0,800;1,400;1,600',
        ],
        'pixel' => [
            'label' => 'Пиксели',
            'about' => '8 бит: ступенчатые рамки, пиксельные значки и график.',
            'fonts' => 'family=Press+Start+2P&family=PT+Mono',
        ],
        'brutal' => [
            'label' => 'Брутал',
            'about' => 'Толстые рамки, жёсткие тени, наклейки и плоский цвет.',
            'fonts' => 'family=Unbounded:wght@500;800;900&family=Golos+Text:wght@400;500;700',
        ],
        'terminal' => [
            'label' => 'Терминал',
            'about' => 'Зелёный люминофор, моноширинный шрифт, ASCII-графика.',
            'fonts' => 'family=JetBrains+Mono:wght@400;700',
        ],
        'resort' => [
            'label' => 'Курорт',
            'about' => 'Неон, закат и пальмы. Оммаж эстетике трейлера GTA VI.',
            'fonts' => 'family=Oswald:wght@500;700&family=Pacifico&family=Onest:wght@400;500;600',
        ],
    ];

    /** Стиль из адреса (?style=), иначе из cookie, иначе по умолчанию. Чужие значения отбрасываем. */
    public static function pick(?string $fromQuery, ?string $fromCookie): string
    {
        foreach ([$fromQuery, $fromCookie] as $value) {
            if ($value !== null && isset(self::ALL[$value])) {
                return $value;
            }
        }

        return self::DEFAULT;
    }

    public static function fontsUrl(string $style): string
    {
        return 'https://fonts.googleapis.com/css2?'.self::ALL[$style]['fonts'].'&display=swap';
    }
}
