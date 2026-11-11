<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Override;
use Phil\Typography\Text\Chars;
use Phil\Typography\Text\Run;

/**
 * A number and its unit are held together: the space in `50 km`, `20 °C`, `75 %` or `12,50 $` becomes a no-break
 * space, so a line never ends between them. No space is added where there was none.
 */
final readonly class Unit implements Fixer
{
    /**
     * The units and signs that follow a number: SI units and their common multiples, data sizes, typographic units,
     * temperatures, percentages and currency signs.
     */
    public const array UNITS = [
        'nm', 'µm', 'mm', 'cm', 'dm', 'm', 'km', 'm²', 'km²', 'm³', 'ha', 'in', 'ft', 'yd', 'mi',
        'mg', 'g', 'kg', 't', 'lb', 'oz', 'ml', 'cl', 'dl', 'l', 'L',
        'ms', 's', 'min', 'h', 'Hz', 'kHz', 'MHz', 'GHz',
        'W', 'kW', 'MW', 'kWh', 'V', 'mV', 'kV', 'A', 'mA', 'Ω', 'kΩ', 'N', 'Pa', 'kPa', 'hPa', 'bar', 'J', 'kJ', 'cal', 'kcal',
        'B', 'kB', 'KB', 'MB', 'GB', 'TB', 'Ko', 'Mo', 'Go', 'To', 'bit', 'kbit', 'Mbit', 'Gbit',
        'px', 'pt', 'em', 'rem', 'dpi', 'ppp',
        'km/h', 'mph', 'm/s',
        '°C', '°F', 'K', '°', '%', '‰',
        '€', '$', '£', '¥', '₩', '₹', '₽', '¢', 'CHF', 'USD', 'EUR', 'CAD', 'GBP', 'JPY',
    ];

    private string $pattern;

    public function __construct()
    {
        $units = implode('|', array_map(static fn (string $unit): string => preg_quote($unit, '/'), self::UNITS));
        $this->pattern = '/(\d) (?=(?:'.$units.')(?![\p{L}\d]))/u';
    }

    #[Override]
    public function fix(Run $run): Run
    {
        return $run->replace(fn (string $text): string => preg_replace($this->pattern, '$1'.Chars::NO_BREAK_SPACE, $text) ?? $text);
    }
}
