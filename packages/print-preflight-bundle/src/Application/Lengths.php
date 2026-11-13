<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Application;

use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflightBundle\Application\Exception\InvalidMeasurement;

/**
 * Lengths and sizes written as text, as the configuration gives them: `3mm`, `0.125in`, `9pt`, `12px`, `0`; `a4`, `a5`,
 * `letter`, `148x210mm`, `8.5 x 11 in`.
 */
final class Lengths
{
    private const string NUMBER = '\d+(?:\.\d+)?|\.\d+';

    private function __construct()
    {
    }

    public static function length(string $text): Length
    {
        if (1 !== preg_match('/\A\s*(?<value>'.self::NUMBER.')\s*(?<unit>mm|in|pt|px)?\s*\z/i', $text, $match)) {
            throw InvalidMeasurement::because(\sprintf('A length is a number and a unit, mm, in, pt or px, such as "3mm"; "%s" is not.', $text));
        }

        $unit = strtolower($match['unit'] ?? '');

        if ('' === $unit && 0.0 !== (float) $match['value']) {
            throw InvalidMeasurement::because(\sprintf('A length other than 0 has a unit, mm, in, pt or px; "%s" has none.', $text));
        }

        return self::in((float) $match['value'], '' === $unit ? 'pt' : $unit);
    }

    public static function size(string $text): Size
    {
        $named = match (strtolower(trim($text))) {
            'a4' => Size::a4(),
            'a5' => Size::a5(),
            'letter' => Size::letter(),
            default => null,
        };

        if ($named instanceof Size) {
            return $named;
        }

        if (1 !== preg_match('/\A\s*(?<width>'.self::NUMBER.')\s*x\s*(?<height>'.self::NUMBER.')\s*(?<unit>mm|in|pt|px)\s*\z/i', $text, $match)) {
            throw InvalidMeasurement::because(\sprintf('A size is a4, a5, letter, or a width and a height with a unit, such as "148x210mm"; "%s" is not.', $text));
        }

        $unit = strtolower($match['unit']);

        return new Size(self::in((float) $match['width'], $unit), self::in((float) $match['height'], $unit));
    }

    /**
     * A length in a unit: `mm`, `in`, `pt` or `px`.
     */
    public static function in(float $value, string $unit): Length
    {
        return match ($unit) {
            'mm' => Length::mm($value),
            'in' => Length::in($value),
            'pt' => Length::pt($value),
            'px' => Length::px($value),
            default => throw InvalidMeasurement::because(\sprintf('The units are mm, in, pt and px; "%s" is not one.', $unit)),
        };
    }
}
