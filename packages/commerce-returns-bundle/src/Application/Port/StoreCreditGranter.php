<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application\Port;

use DateTimeImmutable;
use Phil\Money\Money;

/**
 * Gives a customer store credit for a return. The module's adapter uses `phil/commerce-stored-value-bundle` when it
 * is installed.
 */
interface StoreCreditGranter
{
    public function grant(string $customer, Money $amount, DateTimeImmutable $at, string $note, string $orderNumber): void;
}
