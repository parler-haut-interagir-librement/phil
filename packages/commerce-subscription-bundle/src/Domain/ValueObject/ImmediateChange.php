<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * A change of tier taking effect at once, waiting for the payment of its order: the tier changed to, the moment of the
 * change, how much of the current period is left, and the credit for the part of it not used.
 *
 * - **Within the same period**, the new tier is charged for the part of the period left, and the period keeps its end.
 * - **To a tier of another period**, the new tier is charged for a whole period, which starts at the change.
 *
 * Every share of an amount is computed with `Money::allocate()`, by seconds: the share of the time left is rounded to
 * the nearest minor unit, a tie going to the time left, and nothing is lost or created by rounding.
 */
final readonly class ImmediateChange
{
    private function __construct(
        public string $offerCode,
        public DateTimeImmutable $at,
        public int $secondsLeft,
        public ?int $periodSeconds,
        public Money $credit,
    ) {
    }

    /**
     * @param int|null $periodSeconds the length of the current period, or `null` when the new tier starts a period of
     *                                its own at the change
     */
    public static function of(string $offerCode, DateTimeImmutable $at, int $secondsLeft, ?int $periodSeconds, Money $credit): self
    {
        if ($secondsLeft < 1 || (null !== $periodSeconds && $secondsLeft > $periodSeconds)) {
            throw InvalidValue::because('An immediate change leaves at least one second of the period, and no more than the period.');
        }

        if ($credit->isNegative()) {
            throw InvalidValue::because('The credit of an immediate change is zero or more.');
        }

        return new self($offerCode, $at, $secondsLeft, $periodSeconds, $credit);
    }

    /**
     * Whether the new tier starts a period of its own at the change, rather than keeping the current period's end.
     */
    public function startsAPeriod(): bool
    {
        return null === $this->periodSeconds;
    }

    /**
     * What the new tier is charged, from the price of a whole period of it: the share of the time left, or the whole
     * price when the change starts a period.
     */
    public function charge(Money $wholePeriod): Money
    {
        if (null === $this->periodSeconds || $wholePeriod->isZero()) {
            return $wholePeriod;
        }

        return $wholePeriod->allocate($this->secondsLeft, $this->periodSeconds - $this->secondsLeft)[0];
    }
}
