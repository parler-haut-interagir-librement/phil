<?php

declare(strict_types=1);

namespace Phil\NumberWords\Exception;

use InvalidArgumentException;

/**
 * An amount that is not a plain decimal number of at most 18 digits before the point, or that has more decimals
 * than its currency's sub-unit holds.
 */
final class InvalidAmount extends InvalidArgumentException implements NumberWordsException
{
    public static function notDecimal(string $amount): self
    {
        return new self(\sprintf('"%s" is not a decimal amount such as "120.05" or "-3".', $amount));
    }

    public static function tooPrecise(string $amount, int $digits): self
    {
        return new self(\sprintf('"%s" has more than %d decimals, the precision of its currency; round it first.', $amount, $digits));
    }
}
