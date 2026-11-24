<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

/**
 * Units of one owner that reached a layer: transferred to it, or found there by a count.
 */
final readonly class Movement
{
    public function __construct(
        public string $layer,
        public string $location,
        public Owner $owner,
        public int $quantity,
    ) {
    }
}
