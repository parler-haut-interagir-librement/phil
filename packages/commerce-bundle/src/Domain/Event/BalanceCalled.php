<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

/**
 * The trigger of a deferred sale came: its balance is to pay now.
 */
final readonly class BalanceCalled extends DeferredSaleEvent
{
}
