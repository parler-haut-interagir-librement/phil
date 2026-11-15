<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

/**
 * A deferred sale is paid in full.
 */
final readonly class DeferredSaleSettled extends DeferredSaleEvent
{
}
