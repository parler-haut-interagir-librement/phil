<?php

declare(strict_types=1);

namespace Phil\Measurement\Cldr;

use IntlListFormatter;
use MessageFormatter;
use NumberFormatter;
use Phil\Measurement\Exception\InvalidMeasurement;
use Phil\Measurement\Measurement;
use RoundingMode;
use ValueError;

/**
 * Writes a measurement as a person reads it in a locale, from the unit patterns of CLDR: `12,5 kilogrammes` in
 * French, `12.5 kg` in English short, `5 ft 10 in` for a height split over two units.
 *
 * ```php
 * $formatter = UnitFormatter::cldr();
 * $formatter->format($weight, 'fr_CA', UnitWidth::Long);           // 12,5 kilogrammes
 * $formatter->formatParts([$feet, $inches], 'en', UnitWidth::Narrow);   // 5′ 10″
 * ```
 *
 * **The digits are never a float.** The value is rounded to `$maximumFractionDigits` as a decimal string, and its
 * digits are written with the symbols ICU gives for the locale: its integer part through `NumberFormatter`, which
 * groups it as the locale does, its fraction digits after the locale's decimal separator, in the locale's digits.
 * Only the plural category that picks the pattern (`one`, `few`, `other`) is read by ICU from the rounded number.
 *
 * The extension intl formats units only through the number skeletons of `MessageFormatter`, which take a float:
 * the patterns of CLDR are imported instead, under `data/cldr/patterns/`, so that the value stays exact.
 *
 * A locale falls back to its parent (`fr_CH` to `fr`, `zh_Hant_TW` to `zh_Hant`), then to `en`. A unit without CLDR
 * patterns, an application's own, is written as its value and its code: `12 W.h`. The patterns of 64 locales at most
 * are kept for the life of the formatter, so that locales taken from requests cannot grow a worker's memory.
 */
final class UnitFormatter
{
    /** The locales whose patterns and formatters are kept at most. */
    public const int KEPT = 64;

    /** The fraction digits written at most. */
    public const int MAXIMUM_FRACTION_DIGITS = 18;

    private const string FALLBACK = 'en';

    /** @var array<string, array<string, array<string, array<string, string>>>> */
    private array $patterns = [];

    /** @var array<string, array{integer: NumberFormatter, plural: MessageFormatter, separator: string, zero: int, prefix: string, suffix: string}> */
    private array $locales = [];

    /**
     * @param string $directory the directory of the pattern files, one `<locale>.php` per locale
     */
    public function __construct(
        private readonly string $directory,
    ) {
    }

    /**
     * The patterns of the CLDR release in `data/cldr/VERSION`.
     */
    public static function cldr(): self
    {
        return new self(\dirname(__DIR__, 2).'/data/cldr/patterns');
    }

    /**
     * The measurement in the locale, rounded to `$maximumFractionDigits`, trailing zeros removed.
     */
    public function format(Measurement $measurement, string $locale, UnitWidth $width = UnitWidth::Short, int $maximumFractionDigits = 2, RoundingMode $mode = RoundingMode::HalfEven): string
    {
        if (0 > $maximumFractionDigits || self::MAXIMUM_FRACTION_DIGITS < $maximumFractionDigits) {
            throw InvalidMeasurement::because(\sprintf('A measurement is written with 0 to %d fraction digits, %d asked.', self::MAXIMUM_FRACTION_DIGITS, $maximumFractionDigits));
        }

        $locale = $this->normalized($locale);
        $decimal = $measurement->toDecimal($maximumFractionDigits, $mode);
        $symbols = $this->symbols($locale);
        $unit = $measurement->unit;
        $pattern = null === $unit->cldr ? null : $this->pattern($locale, $width, $unit->cldr);

        if (null === $pattern) {
            return $this->number($decimal, $symbols).' '.$unit->code;
        }

        $category = (string) $symbols['plural']->format([str_contains($decimal, '.') ? (float) $decimal : (int) $decimal]);

        return str_replace('{0}', $this->number($decimal, $symbols), $pattern[$category] ?? $pattern['other']);
    }

    /**
     * The parts of a split measurement (`Measurement::split()`, `UnitPreferences::preferred()`) as one, joined as the
     * locale joins units: `5 ft, 10 in`, `5 pieds et 10 pouces`. The last part is rounded to `$maximumFractionDigits`,
     * the others are whole.
     *
     * @param non-empty-list<Measurement> $parts
     */
    public function formatParts(array $parts, string $locale, UnitWidth $width = UnitWidth::Short, int $maximumFractionDigits = 2, RoundingMode $mode = RoundingMode::HalfEven): string
    {
        $written = array_map(fn (Measurement $part): string => $this->format($part, $locale, $width, $maximumFractionDigits, $mode), $parts);

        if (1 === \count($written)) {
            return $written[0];
        }

        $list = new IntlListFormatter($this->normalized($locale), IntlListFormatter::TYPE_UNITS, match ($width) {
            UnitWidth::Long => IntlListFormatter::WIDTH_WIDE,
            UnitWidth::Short => IntlListFormatter::WIDTH_SHORT,
            UnitWidth::Narrow => IntlListFormatter::WIDTH_NARROW,
        });

        return (string) $list->format($written);
    }

    /**
     * The name of the unit in the locale, `kilogrammes`, or `null` when CLDR has none for it.
     */
    public function unitName(?string $cldr, string $locale, UnitWidth $width = UnitWidth::Long): ?string
    {
        return null === $cldr ? null : ($this->pattern($this->normalized($locale), $width, $cldr)['name'] ?? null);
    }

    /**
     * The patterns of a unit in a width, from the locale or its nearest parent that has them.
     *
     * @return array<string, string>|null
     */
    private function pattern(string $locale, UnitWidth $width, string $cldr): ?array
    {
        foreach ($this->chain($locale) as $candidate) {
            $pattern = $this->patterns($candidate)[$width->value][$cldr] ?? null;

            if (null !== $pattern) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function patterns(string $locale): array
    {
        if (!\array_key_exists($locale, $this->patterns)) {
            if (self::KEPT <= \count($this->patterns)) {
                $this->patterns = [];
            }

            $file = $this->directory.'/'.$locale.'.php';

            /** @var array<string, array<string, array<string, string>>> $read */
            $read = is_file($file) ? require $file : [];
            $this->patterns[$locale] = $read;
        }

        return $this->patterns[$locale];
    }

    /**
     * The locale and its parents, then the fallback: `fr_CA`, `fr`, `en`.
     *
     * @return list<string>
     */
    private function chain(string $locale): array
    {
        $chain = [$locale];

        while (false !== ($underscore = strrpos($locale, '_'))) {
            $locale = substr($locale, 0, $underscore);
            $chain[] = $locale;
        }

        return self::FALLBACK === $locale ? $chain : [...$chain, self::FALLBACK];
    }

    /**
     * The locale with underscores, checked: it names a file.
     */
    private function normalized(string $locale): string
    {
        $locale = str_replace('-', '_', $locale);

        return 1 === preg_match('/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8}){0,3}$/D', $locale)
            ? $locale
            : throw InvalidMeasurement::because(\sprintf('"%s" is not a locale such as "fr_CA".', mb_strcut($locale, 0, 32)));
    }

    /**
     * @return array{integer: NumberFormatter, plural: MessageFormatter, separator: string, zero: int, prefix: string, suffix: string}
     */
    private function symbols(string $locale): array
    {
        if (!isset($this->locales[$locale])) {
            if (self::KEPT <= \count($this->locales)) {
                $this->locales = [];
            }

            try {
                $integer = new NumberFormatter($locale, NumberFormatter::DECIMAL);
                $plural = new MessageFormatter($locale, '{0,plural,zero{zero} one{one} two{two} few{few} many{many} other{other}}');
            } catch (ValueError) {
                // A locale ICU refuses takes the symbols of the fallback, as it takes its patterns.
                return $this->locales[$locale] = $this->symbols(self::FALLBACK);
            }

            $this->locales[$locale] = [
                'integer' => $integer,
                'plural' => $plural,
                'separator' => $integer->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL),
                'zero' => mb_ord($integer->getSymbol(NumberFormatter::ZERO_DIGIT_SYMBOL), 'UTF-8'),
                'prefix' => $integer->getTextAttribute(NumberFormatter::NEGATIVE_PREFIX),
                'suffix' => $integer->getTextAttribute(NumberFormatter::NEGATIVE_SUFFIX),
            ];
        }

        return $this->locales[$locale];
    }

    /**
     * A decimal string in the locale's symbols: `-1234.5` is `-1 234,5` in French.
     *
     * @param array{integer: NumberFormatter, plural: MessageFormatter, separator: string, zero: int, prefix: string, suffix: string} $symbols
     */
    private function number(string $decimal, array $symbols): string
    {
        $negative = str_starts_with($decimal, '-');
        [$integer, $fraction] = explode('.', ltrim($decimal, '-')) + [1 => ''];
        $written = (string) $symbols['integer']->format((int) $integer);

        if ('' !== $fraction) {
            $written .= $symbols['separator'].implode('', array_map(
                static fn (string $digit): string => mb_chr($symbols['zero'] + (int) $digit, 'UTF-8'),
                str_split($fraction),
            ));
        }

        return $negative ? $symbols['prefix'].$written.$symbols['suffix'] : $written;
    }
}
