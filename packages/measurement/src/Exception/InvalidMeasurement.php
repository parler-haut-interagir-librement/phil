<?php

declare(strict_types=1);

namespace Phil\Measurement\Exception;

use InvalidArgumentException;

/**
 * A value that cannot be a measurement: a decimal that is not a number, a measurement written without its unit, or a
 * value its unit cannot hold, such as zero miles per gallon.
 */
final class InvalidMeasurement extends InvalidArgumentException implements MeasurementException
{
    public static function notADecimal(string $value): self
    {
        return new self(\sprintf('"%s" is not a decimal: digits, with an optional "-" and "." followed by digits, are expected.', self::shown($value)));
    }

    public static function notAMeasurement(string $text): self
    {
        return new self(\sprintf('"%s" is not a measurement: a decimal, one space and a unit code are expected, as in "12.5 kg".', self::shown($text)));
    }

    public static function divisionByZero(): self
    {
        return new self('The value has no counterpart: it would be a division by zero, as zero miles per gallon is in litres per 100 km.');
    }

    public static function negativeFractionDigits(int $digits): self
    {
        return new self(\sprintf('A number of fraction digits is zero or more, %d given.', $digits));
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * The input, cut so that an exception message never carries a whole request body.
     */
    private static function shown(string $text): string
    {
        return 64 < \strlen($text) ? mb_strcut($text, 0, 64).'…' : $text;
    }
}
