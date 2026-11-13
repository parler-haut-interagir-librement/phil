<?php

declare(strict_types=1);

namespace Phil\NumberWords\Exception;

use OutOfRangeException;

/**
 * A number beyond what the language spells in words — ICU stops at 10¹⁸ in most languages and writes digits past it,
 * which the library refuses —, a negative ordinal, or a precision of currency outside 0 to 4 decimals.
 */
final class NumberOutOfRange extends OutOfRangeException implements NumberWordsException
{
    public static function tooLarge(int $number, string $locale): self
    {
        return new self(\sprintf('The number %d is too large to spell in words in "%s".', $number, $locale));
    }

    public static function negativeOrdinal(int $number): self
    {
        return new self(\sprintf('An ordinal is zero or more; %d is not.', $number));
    }

    public static function fractionDigits(int $digits): self
    {
        return new self(\sprintf('A currency has 0 to 4 decimals; %d is not supported.', $digits));
    }
}
