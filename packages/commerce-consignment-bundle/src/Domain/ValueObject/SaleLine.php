<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

/**
 * The units of one sale taken from one layer: whose they were and what they left the shop.
 */
final readonly class SaleLine
{
    public function __construct(
        public string $layer,
        public string $location,
        public Owner $owner,
        public int $quantity,
        public Margin $margin,
    ) {
    }
}
