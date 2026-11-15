<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * The amounts the buyer may choose for an amount option: from a minimum of zero or more up to a maximum, in one
 * currency. The maximum is required: an amount without one would let a buyer reach the largest integer, and a line
 * priced past it.
 */
final readonly class AmountRange
{
    private function __construct(
        public Money $minimum,
        public Money $maximum,
    ) {
    }

    public static function of(Money $minimum, Money $maximum): self
    {
        if ($minimum->isNegative()) {
            throw InvalidValue::because('An amount range starts at zero or more.');
        }

        if (!$maximum->currency->equals($minimum->currency) || $maximum->isLessThan($minimum)) {
            throw InvalidValue::because('An amount range ends at its minimum or above, in the same currency.');
        }

        return new self($minimum, $maximum);
    }

    /**
     * The amount a value writes — minor units in digits, no sign, no leading zero, eighteen digits at most — or `null`
     * when it writes none.
     */
    public function read(string $value): ?Money
    {
        return 1 === preg_match('/^(?:0|[1-9]\d{0,17})$/D', $value) ? Money::of((int) $value, $this->minimum->currency) : null;
    }

    /**
     * Why the amount is out of the range, or `null` when it is within.
     */
    public function refusal(Money $amount): ?string
    {
        return match (true) {
            $amount->isLessThan($this->minimum) => \sprintf('the amount is %s or more', $this->minimum->toDecimal()),
            $amount->isGreaterThan($this->maximum) => \sprintf('the amount is %s or less', $this->maximum->toDecimal()),
            default => null,
        };
    }
}
