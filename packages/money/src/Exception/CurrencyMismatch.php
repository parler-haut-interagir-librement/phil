<?php

declare(strict_types=1);

namespace Phil\Money\Exception;

use DomainException;
use Phil\Money\Currency;

/**
 * Two amounts in different currencies met in one operation. No conversion happens here, ever.
 */
final class CurrencyMismatch extends DomainException implements MoneyException
{
    public static function between(Currency $left, Currency $right): self
    {
        return new self(\sprintf('An amount in %s cannot be combined with an amount in %s.', $left->code, $right->code));
    }
}
