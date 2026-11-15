<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

/**
 * The deposit of a deferred sale is paid.
 */
final readonly class DepositReceived extends DeferredSaleEvent
{
}
