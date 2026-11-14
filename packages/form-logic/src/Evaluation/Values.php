<?php

declare(strict_types=1);

namespace Phil\FormLogic\Evaluation;

/**
 * The values logic computes with, and the rules that compare and convert them: those of JsonLogic, which are
 * JavaScript's, except that text is read as a number in decimal notation only. `assets/src/logic.js` holds the same
 * rules, function for function, so the browser and the server reach the same result.
 *
 * A value is `null`, a boolean, a number — an integer up to 2^53, or a finite float —, a text, or a list of values.
 *
 * @internal
 *
 * @phpstan-type Value null|bool|int|float|string|list<mixed>
 */
final class Values
{
    /** The largest integer a JavaScript number holds exactly. */
    public const int SAFE_INTEGER = 9_007_199_254_740_991;

    /** The deepest list read from the data. */
    private const int MAX_DATA_DEPTH = 16;

    /** The pattern of a number written as text, between ASCII spaces. */
    private const string NUMBER = '/^[\t\n\x0B\f\r ]*[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?[\t\n\x0B\f\r ]*$/D';

    private function __construct()
    {
    }

    /**
     * A value of the data, as logic sees it: a number normalized, a non-finite number, an object, a map or anything
     * else that is not a value read as `null`.
     *
     * @return Value
     */
    public static function fromData(mixed $value, int $depth = 0): bool|int|float|string|array|null
    {
        if (\is_int($value) || \is_float($value)) {
            return self::number((float) $value);
        }

        if (\is_string($value) || \is_bool($value)) {
            return $value;
        }

        if (!\is_array($value) || !array_is_list($value) || $depth >= self::MAX_DATA_DEPTH) {
            return null;
        }

        $items = [];

        foreach ($value as $item) {
            $items[] = self::fromData($item, $depth + 1);
        }

        return $items;
    }

    /**
     * A number as logic keeps it: an integral number up to 2^53 as an integer, `-0` as `0`, a non-finite one as `null`.
     */
    public static function number(float $number): int|float|null
    {
        if (!is_finite($number)) {
            return null;
        }

        if (floor($number) === $number && abs($number) <= self::SAFE_INTEGER) {
            return (int) $number;
        }

        return $number;
    }

    /**
     * JsonLogic's truth: `null`, `false`, `0`, `""` and `[]` are false; everything else is true.
     *
     * @param Value $value
     */
    public static function truthy(bool|int|float|string|array|null $value): bool
    {
        return match (true) {
            \is_bool($value) => $value,
            \is_int($value), \is_float($value) => 0.0 !== (float) $value,
            \is_string($value) => '' !== $value,
            \is_array($value) => [] !== $value,
            default => false,
        };
    }

    /**
     * The value as a number: `null` and `false` are 0, `true` is 1, a text in decimal notation is its number — an empty
     * one 0 —, a list is read as its text; anything else is NaN.
     *
     * @param Value $value
     */
    public static function toNumber(bool|int|float|string|array|null $value): float
    {
        if (\is_array($value)) {
            $value = self::toText($value);
        }

        return match (true) {
            \is_string($value) => self::parse($value),
            default => (float) $value,
        };
    }

    /**
     * The value as JavaScript writes it: `null`, `true`, `false`, a number in its shortest form, a list as its items
     * joined by commas, `null` items left empty.
     *
     * @param Value $value
     */
    public static function toText(bool|int|float|string|array|null $value): string
    {
        return match (true) {
            null === $value => 'null',
            true === $value => 'true',
            false === $value => 'false',
            \is_string($value) => $value,
            \is_array($value) => self::join($value),
            default => self::numberToText((float) $value),
        };
    }

    /**
     * JavaScript's `==`: `null` equals only `null`; a boolean is compared as its number; a number and a text are compared
     * as numbers; a list and a scalar are compared as its text; two lists are never equal.
     *
     * @param Value $left
     * @param Value $right
     */
    public static function looselyEqual(bool|int|float|string|array|null $left, bool|int|float|string|array|null $right): bool
    {
        if (null === $left || null === $right) {
            return null === $left && null === $right;
        }

        if (\is_array($left) || \is_array($right)) {
            return (!\is_array($left) || !\is_array($right)) && self::looselyEqual(\is_array($left) ? self::toText($left) : $left, \is_array($right) ? self::toText($right) : $right);
        }

        if (\is_string($left) && \is_string($right)) {
            return $left === $right;
        }

        return self::toNumber($left) === self::toNumber($right);
    }

    /**
     * JavaScript's `===`: the same type and the same value, numbers compared as numbers; two lists are never equal.
     *
     * @param Value $left
     * @param Value $right
     */
    public static function strictlyEqual(bool|int|float|string|array|null $left, bool|int|float|string|array|null $right): bool
    {
        $numbers = (\is_int($left) || \is_float($left)) && (\is_int($right) || \is_float($right));

        if ($numbers) {
            return (float) $left === (float) $right;
        }

        return !\is_array($left) && $left === $right;
    }

    /**
     * Whether `$left` comes before `$right`, or is equal to it with `$orEqual`: two texts in the order of their UTF-16
     * code units, as JavaScript sorts them; anything else as numbers, a NaN coming neither before nor after.
     *
     * @param Value $left
     * @param Value $right
     */
    public static function less(bool|int|float|string|array|null $left, bool|int|float|string|array|null $right, bool $orEqual = false): bool
    {
        $left = \is_array($left) ? self::toText($left) : $left;
        $right = \is_array($right) ? self::toText($right) : $right;

        if (\is_string($left) && \is_string($right)) {
            $order = self::compareTexts($left, $right);

            return $orEqual ? $order <= 0 : $order < 0;
        }

        $a = self::toNumber($left);
        $b = self::toNumber($right);

        return $orEqual ? $a <= $b : $a < $b;
    }

    /**
     * JsonLogic's `in`: a text holds the needle written as text — an empty text holds nothing —; a list holds an item
     * strictly equal to it; anything else holds nothing.
     *
     * @param Value $needle
     * @param Value $haystack
     */
    public static function contains(bool|int|float|string|array|null $needle, bool|int|float|string|array|null $haystack): bool
    {
        if (\is_string($haystack)) {
            return '' !== $haystack && str_contains($haystack, self::toText($needle));
        }

        if (!\is_array($haystack)) {
            return false;
        }

        return array_any($haystack, static fn (mixed $item): bool => self::strictlyEqual(self::fromData($item), $needle));
    }

    /**
     * A number as JavaScript's `String()` writes it: the shortest digits that read back the same number, in plain
     * notation from 10^-6 up to 10^21, in exponent notation outside.
     */
    public static function numberToText(float $number): string
    {
        if (is_nan($number)) {
            return 'NaN';
        }

        if (is_infinite($number)) {
            return \INF === $number ? 'Infinity' : '-Infinity';
        }

        if ($number < 0) {
            return '-'.self::numberToText(-$number);
        }

        [$digits, $point] = self::shortest($number);
        $length = \strlen($digits);

        if ($length <= $point && $point <= 21) {
            return $digits.str_repeat('0', $point - $length);
        }

        if (0 < $point && $point <= 21) {
            return substr($digits, 0, $point).'.'.substr($digits, $point);
        }

        if (-6 < $point && $point <= 0) {
            return '0.'.str_repeat('0', -$point).$digits;
        }

        $exponent = $point - 1;
        $mantissa = 1 === $length ? $digits : $digits[0].'.'.substr($digits, 1);

        return $mantissa.'e'.($exponent < 0 ? '-' : '+').abs($exponent);
    }

    /**
     * The shortest significant digits of a positive finite number that read back the same number, and the position of
     * the decimal point among them.
     *
     * @return array{0: string, 1: int}
     */
    private static function shortest(float $number): array
    {
        $written = \sprintf('%.16e', $number);

        for ($precision = 0; $precision < 17; ++$precision) {
            $candidate = \sprintf('%.'.$precision.'e', $number);

            if ((float) $candidate === $number) {
                $written = $candidate;

                break;
            }
        }

        [$mantissa, $exponent] = explode('e', $written);
        $digits = rtrim(str_replace('.', '', $mantissa), '0');

        return ['' === $digits ? '0' : $digits, (int) $exponent + 1];
    }

    private static function parse(string $text): float
    {
        if (1 !== preg_match(self::NUMBER, $text)) {
            return '' === trim($text, "\t\n\x0B\f\r ") ? 0.0 : \NAN;
        }

        return (float) $text;
    }

    /**
     * @param list<mixed> $items
     */
    private static function join(array $items): string
    {
        $texts = [];

        foreach ($items as $item) {
            $value = self::fromData($item);
            $texts[] = null === $value ? '' : self::toText($value);
        }

        return implode(',', $texts);
    }

    private static function compareTexts(string $left, string $right): int
    {
        // UTF-8 sorts by code point, which UTF-16 code units follow except above U+FFFF: only then is the text converted.
        if (1 === preg_match('/[\x{10000}-\x{10FFFF}]/u', $left.$right)) {
            $left = mb_convert_encoding($left, 'UTF-16BE', 'UTF-8');
            $right = mb_convert_encoding($right, 'UTF-16BE', 'UTF-8');
        }

        return strcmp($left, $right) <=> 0;
    }
}
