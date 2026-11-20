<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Delivery;

/**
 * How much of an order has left.
 */
enum FulfilmentState: string
{
    case Unshipped = 'unshipped';
    case Partial = 'partial';
    case Shipped = 'shipped';
}
