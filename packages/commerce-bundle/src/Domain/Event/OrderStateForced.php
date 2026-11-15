<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\DddBundle\Domain\Event\DomainEvent;

/**
 * Someone forced an order to a state outside its transitions; the journal holds why. `OrderLifecycle` dispatches it
 * by its class.
 */
final readonly class OrderStateForced implements DomainEvent
{
    public function __construct(
        public string $orderNumber,
        public OrderState $from,
        public OrderState $to,
        public string $actor,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    #[Override]
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
