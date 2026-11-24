<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Replenishment;

/**
 * Where a purchase proposal stands: waiting for a person, approved, or rejected. The library never places an order.
 */
enum ProposalState: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
