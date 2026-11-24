<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

/**
 * The units of a SKU one owner holds.
 */
final readonly class OwnerQuantity
{
    public function __construct(
        public Owner $owner,
        public int $quantity,
    ) {
    }
}
