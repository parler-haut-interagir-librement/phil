<?php

declare(strict_types=1);

namespace Phil\Tax\Exception;

use Throwable;

/**
 * Every exception the library throws, so that an application maps them in one place: each one is a mistake of the
 * caller or of its input, never a failure of the library. A currency mismatch keeps its own type,
 * `Phil\Money\Exception\CurrencyMismatch`, which implements `MoneyException`.
 */
interface TaxException extends Throwable
{
}
