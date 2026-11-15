<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

/**
 * A deferred sale was given up before it was paid in full.
 */
final readonly class DeferredSaleCancelled extends DeferredSaleEvent
{
}
