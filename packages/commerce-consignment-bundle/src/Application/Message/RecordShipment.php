<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application\Message;

/**
 * Asks a worker to write to the ledger what an order shipped: its number only, the rest read again from the stores.
 */
final readonly class RecordShipment
{
    public function __construct(
        public string $orderNumber,
    ) {
    }
}
