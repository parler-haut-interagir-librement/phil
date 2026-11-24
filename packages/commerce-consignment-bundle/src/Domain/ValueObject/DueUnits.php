<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;

/**
 * Unsold units of one layer whose consignment is over: due since an instant, to be given back or bought by the shop as
 * the consignor's agreement says.
 */
final readonly class DueUnits
{
    public function __construct(
        public string $layer,
        public string $sku,
        public string $location,
        public Owner $consignor,
        public int $units,
        public DateTimeImmutable $receivedAt,
        public DateTimeImmutable $dueAt,
        public UnsoldPolicy $action,
    ) {
    }
}
