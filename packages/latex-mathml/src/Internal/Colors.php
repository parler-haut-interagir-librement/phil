<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Internal;

/**
 * The colours of `\color` and `\textcolor`: the names every LaTeX installation knows, with the values it gives them,
 * `#rgb` and `#rrggbb`, `[HTML]{rrggbb}` and `[rgb]{r,g,b}`, each written as `#rrggbb`.
 *
 * @internal
 */
final class Colors
{
    public const array NAMES = [
        'black' => '#000000', 'blue' => '#0000ff', 'brown' => '#bf8040', 'cyan' => '#00ffff', 'darkgray' => '#404040',
        'gray' => '#808080', 'green' => '#00ff00', 'lightgray' => '#bfbfbf', 'lime' => '#bfff00', 'magenta' => '#ff00ff',
        'olive' => '#808000', 'orange' => '#ff8000', 'pink' => '#ffbfbf', 'purple' => '#bf0040', 'red' => '#ff0000',
        'teal' => '#008080', 'violet' => '#800080', 'white' => '#ffffff', 'yellow' => '#ffff00',
    ];

    /**
     * The colour as `#rrggbb`, or null when it is not one: a name, `#rgb` or `#rrggbb` without a model, six
     * hexadecimal digits in the model `HTML`, three numbers from 0 to 1 in the model `rgb`.
     */
    public static function hex(string $color, ?string $model): ?string
    {
        $color = trim($color);

        return match ($model) {
            null => self::NAMES[$color] ?? (1 === preg_match('/^#(?:[0-9A-Fa-f]{3}){1,2}$/D', $color) ? self::long(strtolower($color)) : null),
            'HTML' => 1 === preg_match('/^[0-9A-Fa-f]{6}$/D', $color) ? '#'.strtolower($color) : null,
            'rgb' => self::rgb($color),
            default => null,
        };
    }

    private static function long(string $color): string
    {
        return 4 === \strlen($color) ? '#'.$color[1].$color[1].$color[2].$color[2].$color[3].$color[3] : $color;
    }

    private static function rgb(string $color): ?string
    {
        if (1 !== preg_match('/^\s*(0(?:\.[0-9]+)?|1(?:\.0+)?|\.[0-9]+)\s*,\s*(0(?:\.[0-9]+)?|1(?:\.0+)?|\.[0-9]+)\s*,\s*(0(?:\.[0-9]+)?|1(?:\.0+)?|\.[0-9]+)\s*$/D', $color, $match)) {
            return null;
        }

        [, $red, $green, $blue] = $match;

        return \sprintf('#%02x%02x%02x', (int) round((float) $red * 255), (int) round((float) $green * 255), (int) round((float) $blue * 255));
    }
}
