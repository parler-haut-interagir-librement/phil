<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;

/**
 * What a deferred sale asks first: a share of the order's total, in basis points, or a fixed amount, never more than
 * the total.
 */
final readonly class Deposit
{
    /** A whole total, in basis points. */
    public const int WHOLE = 10_000;

    private function __construct(
        public ?int $basisPoints,
        public ?Money $fixed,
    ) {
    }

    /**
     * Nothing first: the whole total is the balance.
     */
    public static function none(): self
    {
        return new self(0, null);
    }

    /**
     * A share of the total, in hundredths of a percent: 2_500 asks a quarter.
     */
    public static function share(int $basisPoints): self
    {
        if ($basisPoints < 0 || $basisPoints > self::WHOLE) {
            throw InvalidValue::because(\sprintf('A deposit is 0 to %d basis points of the total, not %d.', self::WHOLE, $basisPoints));
        }

        return new self($basisPoints, null);
    }

    /**
     * A fixed amount, or the total when the total is less.
     */
    public static function fixed(Money $amount): self
    {
        if ($amount->isNegative()) {
            throw InvalidValue::because('A deposit is not a negative amount.');
        }

        return new self(null, $amount);
    }

    /**
     * The deposit of a total. A share is rounded as `Money::allocate()` shares the total between the deposit and the balance.
     *
     * @throws CurrencyMismatch when a fixed deposit is in another currency than the total
     */
    public function of(Money $total): Money
    {
        if ($this->fixed instanceof Money) {
            return $this->fixed->isGreaterThan($total) ? $total : $this->fixed;
        }

        if (!$total->isPositive()) {
            return Money::zero($total->currency);
        }

        $share = (int) $this->basisPoints;

        return $total->allocate($share, self::WHOLE - $share)[0];
    }
}
