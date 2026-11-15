<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

use DateTimeImmutable;
use Override;
use Phil\DddBundle\Domain\Event\DomainEvent;

/**
 * A fact of a deferred sale, known by its order's number: a listener reads what else it needs from its own store.
 * `DeferredSales` dispatches each one, by its class, once the stock ports have run.
 */
abstract readonly class DeferredSaleEvent implements DomainEvent
{
    final public function __construct(
        public string $orderNumber,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    #[Override]
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
