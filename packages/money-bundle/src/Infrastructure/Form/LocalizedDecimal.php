<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Form;

use Locale;
use NumberFormatter;

/**
 * A decimal amount as a person writes it in a locale, read and written without a float.
 *
 * Reading takes the locale's decimal separator as the decimal point, and also a dot when the dot is not the
 * locale's grouping separator. Grouping is accepted only where the locale puts it: the locale's grouping separator,
 * or a space, between groups of the locale's sizes before the decimal point — `1 234,56` in `fr_CA`, `1,234.56` in
 * `en`, `1.234,56` in `de`, `12,34,567.50` in `en_IN`. A separator anywhere else makes the input unreadable rather
 * than silently dropped: `12,50` in `en` and `12.50` in `de` are refused, not read as 1250. The minus sign `−`
 * stands for `-`.
 *
 * Writing only swaps the dot for the locale's separator, without grouping, so that the field reads back what it
 * shows.
 *
 * The symbols of each locale are read from ICU once per process, those of 64 locales at most: past them, they are
 * read again, so that locales taken from requests cannot grow the memory of a worker without end.
 */
final class LocalizedDecimal
{
    private const array SPACES = [' ', "\u{00A0}", "\u{202F}"];

    /** The locales whose symbols are kept at most. */
    private const int KEPT = 64;

    /**
     * @var array<string, array{separator: string, grouping: string, primary: int, secondary: int}>
     */
    private static array $symbols = [];

    /**
     * The normalized decimal (`-1234.56`), or `null` when the input is not one.
     */
    public static function parse(string $input, ?string $locale = null): ?string
    {
        $symbols = self::symbols($locale ?? Locale::getDefault());
        $text = str_replace([...self::SPACES, "\u{2212}"], [' ', ' ', ' ', '-'], trim($input));

        // The input's spaces are all plain by now: a grouping by a no-break space is the plain one, and a separator
        // named twice is only an alternative written twice in the pattern.
        $groupings = [' ', $symbols['grouping']];
        $points = array_filter(
            [$symbols['separator'], '.'],
            static fn (string $point): bool => !\in_array($point, $groupings, true),
        );

        if ([] === $points) {
            return null;
        }

        $integer = '\d+';

        if (0 < $symbols['primary']) {
            $integer .= \sprintf(
                '|\d{1,%1$d}(?P<group>%3$s)(?:\d{%1$d}(?P=group))*\d{%2$d}',
                0 < $symbols['secondary'] ? $symbols['secondary'] : $symbols['primary'],
                $symbols['primary'],
                self::alternatives($groupings),
            );
        }

        $pattern = \sprintf('/^(?P<sign>-?)(?P<integer>%s)(?:(?:%s)(?P<fraction>\d+))?$/u', $integer, self::alternatives($points));

        if (1 !== preg_match($pattern, $text, $matches)) {
            return null;
        }

        $integer = preg_replace('/\D/', '', $matches['integer']) ?? '';
        $fraction = $matches['fraction'] ?? '';

        return $matches['sign'].$integer.('' === $fraction ? '' : '.'.$fraction);
    }

    public static function format(string $decimal, ?string $locale = null): string
    {
        return str_replace('.', self::symbols($locale ?? Locale::getDefault())['separator'], $decimal);
    }

    /**
     * @param array<string> $texts
     */
    private static function alternatives(array $texts): string
    {
        return implode('|', array_map(static fn (string $text): string => preg_quote($text, '/'), $texts));
    }

    /**
     * @return array{separator: string, grouping: string, primary: int, secondary: int}
     */
    private static function symbols(string $locale): array
    {
        if (!isset(self::$symbols[$locale])) {
            if (\count(self::$symbols) >= self::KEPT) {
                self::$symbols = [];
            }

            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);

            self::$symbols[$locale] = [
                'separator' => $formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL),
                'grouping' => $formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL),
                'primary' => $formatter->getAttribute(NumberFormatter::GROUPING_SIZE),
                'secondary' => $formatter->getAttribute(NumberFormatter::SECONDARY_GROUPING_SIZE),
            ];
        }

        return self::$symbols[$locale];
    }
}
