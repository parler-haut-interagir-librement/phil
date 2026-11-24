<?php

declare(strict_types=1);

namespace Phil\Pricing\Exception;

use Throwable;

/**
 * Every exception of the library: one `catch` covers them all. Two amounts in different currencies still raise
 * `Phil\Money\Exception\CurrencyMismatch`, from `phil/money`.
 */
interface PricingException extends Throwable
{
}
