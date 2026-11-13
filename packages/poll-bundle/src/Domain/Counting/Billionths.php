<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Counting;

use InvalidArgumentException;
use Stringable;

/**
 * An exact non-negative rational number of denominator one billion, held as its integer numerator: the votes, the
 * quota, the keep factors and the surpluses of a single transferable vote. No float enters it; every rounding of a
 * count says which way it goes.
 */
final readonly class Billionths implements Stringable
{
    public const int SCALE = 1_000_000_000;

    private function __construct(
        public int $numerator,
    ) {
    }

    /**
     * The number of this many billionths.
     *
     * @throws InvalidArgumentException when the numerator is negative
     */
    public static function of(int $numerator): self
    {
        if ($numerator < 0) {
            throw new InvalidArgumentException(\sprintf('A count holds no negative number, not %d billionths.', $numerator));
        }

        return new self($numerator);
    }

    /**
     * The number of this many whole units.
     *
     * @throws InvalidArgumentException when the number is negative or above a billion units
     */
    public static function whole(int $units): self
    {
        if ($units > self::SCALE) {
            throw new InvalidArgumentException(\sprintf('A count holds at most %d whole units, not %d.', self::SCALE, $units));
        }

        return self::of($units * self::SCALE);
    }

    /**
     * The number written with its nine decimals, as a count publishes it: `4.714288797`.
     */
    public function toString(): string
    {
        return \sprintf('%d.%09d', intdiv($this->numerator, self::SCALE), $this->numerator % self::SCALE);
    }

    public function equals(self $other): bool
    {
        return $this->numerator === $other->numerator;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
