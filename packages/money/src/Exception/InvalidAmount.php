<?php

declare(strict_types=1);

namespace Phil\Money\Exception;

use InvalidArgumentException;

/**
 * An amount that cannot be one: a decimal that is not a number, finer than its currency counts, or outside the
 * range of a native integer.
 */
final class InvalidAmount extends InvalidArgumentException implements MoneyException
{
    public static function notADecimal(string $amount): self
    {
        return new self(\sprintf('"%s" is not a decimal amount: digits, with an optional "-" and "." are expected.', $amount));
    }

    public static function tooPrecise(string $amount, string $currency, int $fractionDigits): self
    {
        return new self(\sprintf('"%s" has more decimals than %s counts (%d).', $amount, $currency, $fractionDigits));
    }

    public static function outOfRange(): self
    {
        return new self('The amount leaves the range of a native integer.');
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
