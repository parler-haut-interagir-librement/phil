<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application;

use Exception;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\Money\Money;

/**
 * How a return was settled: what it was worth, what went back to which payment, what the payments could not take
 * back, what became store credit, the payment of the exchange, and the refunds a gateway failed to make.
 */
final readonly class Settlement
{
    /**
     * @param list<array{Payment, Money}>            $refunds
     * @param list<array{Payment, Money, Exception}> $failures the refunds a gateway refused or failed; their amount
     *                                                         is in the shortfall unless another payment took it
     */
    public function __construct(
        public Money $value,
        public array $refunds,
        public Money $shortfall,
        public Money $credited,
        public ?Payment $exchange,
        public array $failures = [],
    ) {
    }

    public function refunded(): Money
    {
        $sum = Money::zero($this->value->currency);

        foreach ($this->refunds as [, $amount]) {
            $sum = $sum->add($amount);
        }

        return $sum;
    }
}
