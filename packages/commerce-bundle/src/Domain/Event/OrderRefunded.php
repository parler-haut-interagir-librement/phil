<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Event;

use DateTimeImmutable;
use Override;
use Phil\DddBundle\Domain\Event\DomainEvent;
use Phil\Money\Money;

/**
 * A refund of an order was executed: the processor was asked for the money, or an offline refund recorded. What an
 * activity feed, a loyalty programme or a returns module listens to, without the commerce knowing them. It carries
 * identifiers and the amount only — the order's number, the refund's and the payment's identifiers, the order's
 * channel and its customer's stable identifier — and a listener reads what else it needs from its own store.
 * `Refunds` dispatches it by its class, once per refund executed.
 */
final readonly class OrderRefunded implements DomainEvent
{
    /**
     * @param string|null $channel  the order's channel; `null` when it has none, or `Refunds` reads no order
     * @param string|null $customer the order's customer; `null` for a guest, or when `Refunds` reads no order
     */
    public function __construct(
        public string $orderNumber,
        public string $refundId,
        public string $paymentId,
        public ?string $channel,
        public ?string $customer,
        public Money $amount,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    #[Override]
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
