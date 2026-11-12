<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Colour;

/**
 * Reads a colour of CSS: `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`, `rgb()` and `rgba()`, `hsl()` and `hsla()` with commas or
 * spaces and an opacity after `/`, `oklch()` with an opacity after `/`, the named colours of CSS Color 4 and
 * `transparent`, in any case, `!important` ignored. An `oklch()` colour outside sRGB has each channel clipped to it.
 * A colour it cannot compute — `var()`, `color-mix()`, a system colour — is `null`: the check leaves the text alone
 * rather than guess; `isUnresolved()` tells `var()` and `color-mix()` apart, which only a browser resolves.
 */
final class ColourParser
{
    /**
     * The values that take the colour of the parent: the check looks further up.
     */
    public const array INHERITED = ['inherit', 'currentcolor', 'unset', 'initial', 'revert', 'revert-layer'];

    /**
     * @var array<string, string>
     */
    private const array NAMED = [
        'aliceblue' => 'f0f8ff', 'antiquewhite' => 'faebd7', 'aqua' => '00ffff', 'aquamarine' => '7fffd4',
        'azure' => 'f0ffff', 'beige' => 'f5f5dc', 'bisque' => 'ffe4c4', 'black' => '000000',
        'blanchedalmond' => 'ffebcd', 'blue' => '0000ff', 'blueviolet' => '8a2be2', 'brown' => 'a52a2a',
        'burlywood' => 'deb887', 'cadetblue' => '5f9ea0', 'chartreuse' => '7fff00', 'chocolate' => 'd2691e',
        'coral' => 'ff7f50', 'cornflowerblue' => '6495ed', 'cornsilk' => 'fff8dc', 'crimson' => 'dc143c',
        'cyan' => '00ffff', 'darkblue' => '00008b', 'darkcyan' => '008b8b', 'darkgoldenrod' => 'b8860b',
        'darkgray' => 'a9a9a9', 'darkgreen' => '006400', 'darkgrey' => 'a9a9a9', 'darkkhaki' => 'bdb76b',
        'darkmagenta' => '8b008b', 'darkolivegreen' => '556b2f', 'darkorange' => 'ff8c00', 'darkorchid' => '9932cc',
        'darkred' => '8b0000', 'darksalmon' => 'e9967a', 'darkseagreen' => '8fbc8f', 'darkslateblue' => '483d8b',
        'darkslategray' => '2f4f4f', 'darkslategrey' => '2f4f4f', 'darkturquoise' => '00ced1', 'darkviolet' => '9400d3',
        'deeppink' => 'ff1493', 'deepskyblue' => '00bfff', 'dimgray' => '696969', 'dimgrey' => '696969',
        'dodgerblue' => '1e90ff', 'firebrick' => 'b22222', 'floralwhite' => 'fffaf0', 'forestgreen' => '228b22',
        'fuchsia' => 'ff00ff', 'gainsboro' => 'dcdcdc', 'ghostwhite' => 'f8f8ff', 'gold' => 'ffd700',
        'goldenrod' => 'daa520', 'gray' => '808080', 'green' => '008000', 'greenyellow' => 'adff2f', 'grey' => '808080',
        'honeydew' => 'f0fff0', 'hotpink' => 'ff69b4', 'indianred' => 'cd5c5c', 'indigo' => '4b0082',
        'ivory' => 'fffff0', 'khaki' => 'f0e68c', 'lavender' => 'e6e6fa', 'lavenderblush' => 'fff0f5',
        'lawngreen' => '7cfc00', 'lemonchiffon' => 'fffacd', 'lightblue' => 'add8e6', 'lightcoral' => 'f08080',
        'lightcyan' => 'e0ffff', 'lightgoldenrodyellow' => 'fafad2', 'lightgray' => 'd3d3d3', 'lightgreen' => '90ee90',
        'lightgrey' => 'd3d3d3', 'lightpink' => 'ffb6c1', 'lightsalmon' => 'ffa07a', 'lightseagreen' => '20b2aa',
        'lightskyblue' => '87cefa', 'lightslategray' => '778899', 'lightslategrey' => '778899',
        'lightsteelblue' => 'b0c4de', 'lightyellow' => 'ffffe0', 'lime' => '00ff00', 'limegreen' => '32cd32',
        'linen' => 'faf0e6', 'magenta' => 'ff00ff', 'maroon' => '800000', 'mediumaquamarine' => '66cdaa',
        'mediumblue' => '0000cd', 'mediumorchid' => 'ba55d3', 'mediumpurple' => '9370db', 'mediumseagreen' => '3cb371',
        'mediumslateblue' => '7b68ee', 'mediumspringgreen' => '00fa9a', 'mediumturquoise' => '48d1cc',
        'mediumvioletred' => 'c71585', 'midnightblue' => '191970', 'mintcream' => 'f5fffa', 'mistyrose' => 'ffe4e1',
        'moccasin' => 'ffe4b5', 'navajowhite' => 'ffdead', 'navy' => '000080', 'oldlace' => 'fdf5e6',
        'olive' => '808000', 'olivedrab' => '6b8e23', 'orange' => 'ffa500', 'orangered' => 'ff4500',
        'orchid' => 'da70d6', 'palegoldenrod' => 'eee8aa', 'palegreen' => '98fb98', 'paleturquoise' => 'afeeee',
        'palevioletred' => 'db7093', 'papayawhip' => 'ffefd5', 'peachpuff' => 'ffdab9', 'peru' => 'cd853f',
        'pink' => 'ffc0cb', 'plum' => 'dda0dd', 'powderblue' => 'b0e0e6', 'purple' => '800080',
        'rebeccapurple' => '663399', 'red' => 'ff0000', 'rosybrown' => 'bc8f8f', 'royalblue' => '4169e1',
        'saddlebrown' => '8b4513', 'salmon' => 'fa8072', 'sandybrown' => 'f4a460', 'seagreen' => '2e8b57',
        'seashell' => 'fff5ee', 'sienna' => 'a0522d', 'silver' => 'c0c0c0', 'skyblue' => '87ceeb',
        'slateblue' => '6a5acd', 'slategray' => '708090', 'slategrey' => '708090', 'snow' => 'fffafa',
        'springgreen' => '00ff7f', 'steelblue' => '4682b4', 'tan' => 'd2b48c', 'teal' => '008080',
        'thistle' => 'd8bfd8', 'tomato' => 'ff6347', 'turquoise' => '40e0d0', 'violet' => 'ee82ee', 'wheat' => 'f5deb3',
        'white' => 'ffffff', 'whitesmoke' => 'f5f5f5', 'yellow' => 'ffff00', 'yellowgreen' => '9acd32',
    ];

    private const string FUNCTION = '/^(rgba?|hsla?|oklch)\(([^()]*)\)$/D';

    private const string HEX = '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/D';

    public static function isInherited(string $value): bool
    {
        return \in_array(self::normalize($value), self::INHERITED, true);
    }

    /**
     * Whether the value holds a `var()` or a `color-mix()`, whose colour depends on the style sheets of the page.
     */
    public static function isUnresolved(string $value): bool
    {
        return 1 === preg_match('/(?:^|[^a-z-])(?:var|color-mix)\(/', self::normalize($value));
    }

    public static function parse(string $value): ?Colour
    {
        $value = self::normalize($value);

        if ('transparent' === $value) {
            return new Colour(0, 0, 0, 0);
        }

        if (isset(self::NAMED[$value])) {
            return self::hex(self::NAMED[$value]);
        }

        if (1 === preg_match(self::HEX, $value)) {
            return self::hex(substr($value, 1));
        }

        if (1 !== preg_match(self::FUNCTION, $value, $match)) {
            return null;
        }

        return match ($match[1]) {
            'oklch' => self::oklch($match[2]),
            'hsl', 'hsla' => self::hsl($match[2]),
            default => self::rgb($match[2]),
        };
    }

    private static function normalize(string $value): string
    {
        return trim(str_replace('!important', '', strtolower($value)));
    }

    private static function hex(string $digits): Colour
    {
        if (\in_array(\strlen($digits), [3, 4], true)) {
            $digits = implode('', array_map(static fn (string $digit): string => $digit.$digit, str_split($digits)));
        }

        $channels = array_map(hexdec(...), str_split($digits, 2));

        return new Colour((float) $channels[0], (float) $channels[1], (float) $channels[2], isset($channels[3]) ? $channels[3] / 255 : 1.0);
    }

    private static function rgb(string $arguments): ?Colour
    {
        $parts = self::arguments($arguments);

        if (null === $parts) {
            return null;
        }

        $red = self::number($parts[0], 255.0);
        $green = self::number($parts[1], 255.0);
        $blue = self::number($parts[2], 255.0);
        $alpha = self::alpha($parts[3] ?? null);

        if (\in_array(null, [$red, $green, $blue, $alpha], true)) {
            return null;
        }

        return new Colour(self::clamp($red, 255.0), self::clamp($green, 255.0), self::clamp($blue, 255.0), $alpha);
    }

    private static function hsl(string $arguments): ?Colour
    {
        $parts = self::arguments($arguments);

        if (null === $parts) {
            return null;
        }

        $hue = self::hue($parts[0]);
        $saturation = self::number($parts[1], 100.0);
        $lightness = self::number($parts[2], 100.0);
        $alpha = self::alpha($parts[3] ?? null);

        if (\in_array(null, [$hue, $saturation, $lightness, $alpha], true)) {
            return null;
        }

        $saturation = self::clamp($saturation / 100, 1.0);
        $lightness = self::clamp($lightness / 100, 1.0);
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $channel = static function (int $n) use ($hue, $lightness, $chroma): float {
            $k = fmod(fmod($n + $hue / 30, 12) + 12, 12);

            return 255 * ($lightness - $chroma / 2 * max(-1, min($k - 3, 9 - $k, 1)));
        };

        return new Colour($channel(0), $channel(8), $channel(4), $alpha);
    }

    /**
     * An OKLCH colour of CSS Color 4: its lightness from 0 to 1 or 0% to 100%, its chroma from 0, 100% being 0.4, and its
     * hue, through OKLab and linear sRGB to sRGB.
     */
    private static function oklch(string $arguments): ?Colour
    {
        $parts = self::arguments($arguments);

        if (null === $parts) {
            return null;
        }

        $lightness = self::number($parts[0], 1.0);
        $chroma = self::number($parts[1], 0.4);
        $hue = self::hue($parts[2]);
        $alpha = self::alpha($parts[3] ?? null);

        if (\in_array(null, [$lightness, $chroma, $hue, $alpha], true)) {
            return null;
        }

        $lightness = self::clamp($lightness, 1.0);
        $chroma = max(0.0, $chroma);
        $a = $chroma * cos(deg2rad($hue));
        $b = $chroma * sin(deg2rad($hue));
        $long = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $medium = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $short = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return new Colour(
            self::encoded(4.0767416621 * $long - 3.3077115913 * $medium + 0.2309699292 * $short),
            self::encoded(-1.2684380046 * $long + 2.6097574011 * $medium - 0.3413193965 * $short),
            self::encoded(-0.0041960863 * $long - 0.7034186147 * $medium + 1.7076147010 * $short),
            $alpha,
        );
    }

    /**
     * A linear channel of sRGB, clipped to it, encoded from 0 to 255.
     */
    private static function encoded(float $linear): float
    {
        $linear = self::clamp($linear, 1.0);

        return 255 * ($linear <= 0.0031308 ? 12.92 * $linear : 1.055 * $linear ** (1 / 2.4) - 0.055);
    }

    /**
     * The three channels and the optional opacity, separated by commas or spaces, the opacity after `/` or a fourth
     * comma.
     *
     * @return ?list<string>
     */
    private static function arguments(string $arguments): ?array
    {
        $parts = preg_split('/\s*[\s,\/]\s*/', trim($arguments));

        return false !== $parts && \count($parts) >= 3 && \count($parts) <= 4 ? $parts : null;
    }

    /**
     * A number, or a percentage of `$full`; `none` is zero.
     */
    private static function number(string $value, float $full): ?float
    {
        if ('none' === $value) {
            return 0.0;
        }

        if (str_ends_with($value, '%')) {
            $percent = self::plain(substr($value, 0, -1));

            return null === $percent ? null : $percent * $full / 100;
        }

        return self::plain($value);
    }

    private static function alpha(?string $value): ?float
    {
        if (null === $value) {
            return 1.0;
        }

        $alpha = self::number($value, 1.0);

        return null === $alpha ? null : self::clamp($alpha, 1.0);
    }

    /**
     * A hue in degrees, from a number or an angle in `deg`, `grad`, `rad` or `turn`.
     */
    private static function hue(string $value): ?float
    {
        $factor = 1.0;

        foreach (['grad' => 0.9, 'turn' => 360.0, 'deg' => 1.0, 'rad' => 180 / \M_PI] as $unit => $unitFactor) {
            if (str_ends_with($value, $unit)) {
                $value = substr($value, 0, -\strlen($unit));
                $factor = $unitFactor;

                break;
            }
        }

        $number = 'none' === $value ? 0.0 : self::plain($value);

        return null === $number ? null : $number * $factor;
    }

    private static function clamp(float $value, float $maximum): float
    {
        return max(0.0, min($maximum, $value));
    }

    private static function plain(string $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
