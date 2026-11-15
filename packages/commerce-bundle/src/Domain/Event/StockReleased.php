<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

/**
 * The stock a cancelled deferred sale held is released: the goods may sell to someone else.
 */
final readonly class StockReleased extends DeferredSaleEvent
{
}
