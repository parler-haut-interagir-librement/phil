<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

/**
 * What one sweep of `OrderExpiry` did: the orders it expired and those it deleted, by number.
 */
final readonly class ExpirySweep
{
    /**
     * @param list<string> $expired
     * @param list<string> $deleted
     */
    public function __construct(
        public array $expired,
        public array $deleted,
    ) {
    }
}
