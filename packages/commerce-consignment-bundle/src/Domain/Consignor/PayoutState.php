<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Consignor;

/**
 * Where the shop stands with a consignor: what it owes against what it paid.
 */
enum PayoutState: string
{
    /** Something is owed and nothing was paid. */
    case Due = 'due';
    /** Something was paid, and something is still owed. */
    case PartlyPaid = 'partly_paid';
    /** Nothing is owed: everything was paid, or nothing was ever owed. */
    case Paid = 'paid';
}
