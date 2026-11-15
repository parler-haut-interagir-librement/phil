<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

use DateTimeImmutable;
use Override;
use Phil\DddBundle\Domain\Event\DomainEvent;
use Phil\Money\Money;

/**
 * An order is paid in full: what a loyalty programme, an activity feed or a wish list listens to, without the
 * commerce knowing them. It carries identifiers and the amount only — the order's number, its channel, its customer's
 * stable identifier — and a listener reads what else it needs from its own store. `Payments` dispatches it by its
 * class, once per order, when the payment that completes it is applied.
 */
final readonly class OrderPurchased implements DomainEvent
{
    public function __construct(
        public string $orderNumber,
        public ?string $channel,
        public ?string $customer,
        public Money $total,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    #[Override]
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
