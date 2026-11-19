<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Stock;

/**
 * Why a stock level moved.
 */
enum MovementKind: string
{
    case Received = 'received';
    case Counted = 'counted';
    case TransferredOut = 'transferred_out';
    case TransferredIn = 'transferred_in';
    case Reserved = 'reserved';
    case Backordered = 'backordered';
    case Released = 'released';
    case Shipped = 'shipped';
}
