<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\DddBundle\Domain\Event\DomainEvent;

/**
 * An order was given up: cancelled through the workflow, expired unpaid, or forced to `cancelled`. What an activity
 * feed or a loyalty programme listens to, to withdraw what the order earned, without the commerce knowing them. It
 * carries identifiers only — the order's number, the state it left, its channel, its customer's stable identifier, who
 * cancelled it — and a listener reads what else it needs from its own store. `OrderLifecycle` dispatches it by its
 * class, once per cancellation.
 */
final readonly class OrderCancelled implements DomainEvent
{
    /**
     * @param bool        $expired whether the order expired unpaid rather than being cancelled by someone
     * @param string|null $actor   the stable identifier of who cancelled it; `null` for the application itself
     */
    public function __construct(
        public string $orderNumber,
        public OrderState $from,
        public ?string $channel,
        public ?string $customer,
        public bool $expired,
        public ?string $actor,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    #[Override]
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
