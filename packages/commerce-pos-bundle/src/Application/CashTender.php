<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\Money\Money;

/**
 * Cash taken for a payment: the payment, the cash due once rounded, what the customer handed over, and the change.
 */
final readonly class CashTender
{
    public function __construct(
        public Payment $payment,
        public Money $due,
        public Money $tendered,
        public Money $change,
    ) {
    }
}
