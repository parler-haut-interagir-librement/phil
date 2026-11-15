<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

/**
 * A placed order is now paid later, under a deferred sale policy.
 */
final readonly class DeferredSaleOpened extends DeferredSaleEvent
{
}
