<?php

declare(strict_types=1);

namespace Phil\UxCss\Token;

/**
 * The pieces of CSS values `DesignTokens` reads: lists, font families, cubic Béziers, and numbers as the JSON writes
 * them.
 *
 * @internal
 */
final class CssValue
{
    /**
     * @return array{string, list<string>}|null
     */
    public static function fontFamily(string $css): ?array
    {
        $families = [];

        foreach (self::split($css, ',') as $family) {
            $family = 1 === preg_match('/^(["\'])(.*)\1\z/s', $family, $match) ? $match[2] : $family;

            if ('' === $family) {
                return null;
            }

            $families[] = $family;
        }

        return ['fontFamily', $families];
    }

    /**
     * @return array{string, list<float|int>}|null
     */
    public static function cubicBezier(string $css): ?array
    {
        $number = '\s*([+-]?(?:\d+\.?\d*|\.\d+))\s*';

        if (1 !== preg_match('/^cubic-bezier\('.$number.','.$number.','.$number.','.$number.'\)$/iD', $css, $match)) {
            return null;
        }

        $points = array_map(static fn (string $point): float|int => self::number((float) $point), \array_slice($match, 1));

        if ($points[0] < 0 || $points[0] > 1 || $points[2] < 0 || $points[2] > 1) {
            return null;
        }

        return ['cubicBezier', $points];
    }

    /**
     * The pieces of a list, cut on a separator outside parentheses and quotes, each trimmed, the empty ones dropped
     * when the separator is a space.
     *
     * @return list<string>
     */
    public static function split(string $list, string $separator): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;

        foreach (str_split($list) as $character) {
            if (null !== $quote) {
                $quote = $character === $quote ? null : $quote;
            } elseif ('"' === $character || "'" === $character) {
                $quote = $character;
            } elseif ('(' === $character) {
                ++$depth;
            } elseif (')' === $character) {
                --$depth;
            } elseif (0 === $depth && ($character === $separator || (' ' === $separator && str_contains(" \t\n\r\f", $character)))) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $parts[] = trim($current);

        return ' ' === $separator ? array_values(array_filter($parts, static fn (string $part): bool => '' !== $part)) : $parts;
    }

    public static function number(float $value): float|int
    {
        $rounded = round($value, 8);

        return 0.0 === fmod($rounded, 1.0) && abs($rounded) < 1e15 ? (int) $rounded : $rounded;
    }
}
