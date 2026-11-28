<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables;

/**
 * One deliverable: a benefit owed to a member for a period.
 */
final readonly class DeliverableKey
{
    public function __construct(
        public Period $period,
        public string $benefit,
        public string $member,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->period->equals($other->period) && $this->benefit === $other->benefit && $this->member === $other->member;
    }
}
