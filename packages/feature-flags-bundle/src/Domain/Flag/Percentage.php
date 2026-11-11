<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A whole percentage, from 0 to 100 inclusive.
 *
 * Whole numbers only: a bucket is one of a hundred (`Bucket`), so a fractional rollout would promise a
 * precision the bucketing does not have.
 */
final readonly class Percentage
{
    private function __construct(
        public int $value,
    ) {
    }

    public static function of(int $value): self
    {
        if ($value < 0 || $value > 100) {
            throw InvalidValue::because(\sprintf('A percentage is between 0 and 100; got %d.', $value));
        }

        return new self($value);
    }

    /**
     * A bucket is inside the rollout when it is strictly below the percentage: 0 % admits no bucket, 100 %
     * admits all hundred, and raising the percentage only ever adds buckets.
     */
    public function admits(Bucket $bucket): bool
    {
        return $bucket->value < $this->value;
    }
}
