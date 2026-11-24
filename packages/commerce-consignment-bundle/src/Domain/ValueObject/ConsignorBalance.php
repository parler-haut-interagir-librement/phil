<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use Phil\CommerceConsignmentBundle\Domain\Consignor\PayoutState;
use Phil\Money\Money;

/**
 * What the shop owes a consignor in one currency, from the ledger, against what it paid them: the balance is still owed
 * when positive, and the consignor owes the shop when negative — a customer's return after a payout.
 */
final readonly class ConsignorBalance
{
    private function __construct(
        public Owner $consignor,
        public Money $owed,
        public Money $paid,
        public Money $balance,
        public PayoutState $state,
    ) {
    }

    public static function of(Owner $consignor, Money $owed, Money $paid): self
    {
        $balance = $owed->subtract($paid);

        $state = match (true) {
            !$balance->isPositive() => PayoutState::Paid,
            $paid->isPositive() => PayoutState::PartlyPaid,
            default => PayoutState::Due,
        };

        return new self($consignor, $owed, $paid, $balance, $state);
    }
}
