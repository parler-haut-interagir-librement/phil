<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Cooperative;

use Phil\Money\Money;

/**
 * A participant's part of a cooperative bundle's revenue, or the charity's when the participant is null.
 */
final readonly class CooperativeShare
{
    public function __construct(
        public ?string $participant,
        public Money $amount,
    ) {
    }

    public function isCharity(): bool
    {
        return null === $this->participant;
    }
}
