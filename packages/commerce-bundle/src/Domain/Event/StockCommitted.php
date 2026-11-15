<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

/**
 * The stock of a deferred sale's order is committed to its buyer: the goods are held for them.
 */
final readonly class StockCommitted extends DeferredSaleEvent
{
}
