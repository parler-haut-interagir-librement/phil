<?php

declare(strict_types=1);

namespace Phil\Money\Exception;

use InvalidArgumentException;

/**
 * A code that names no ISO 4217 currency the Unicode CLDR data knows.
 */
final class UnknownCurrency extends InvalidArgumentException implements MoneyException
{
    public static function code(string $code): self
    {
        return new self(\sprintf('"%s" is not a known ISO 4217 currency code.', $code));
    }
}
