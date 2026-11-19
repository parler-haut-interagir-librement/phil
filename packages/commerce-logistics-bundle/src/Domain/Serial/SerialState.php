<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Serial;

/**
 * Where a serialised unit is.
 */
enum SerialState: string
{
    /** On a shelf, at its location. */
    case InStock = 'in_stock';

    /** Gone with an order. */
    case Shipped = 'shipped';
}
