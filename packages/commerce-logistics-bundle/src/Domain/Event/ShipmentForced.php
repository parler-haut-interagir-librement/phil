<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Event;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\DddBundle\Domain\Event\DomainEvent;

/**
 * Someone shipped an order that no authorization let leave: what an audit trail or an alert listens to. It carries
 * identifiers only — the shipment, the order and who forced it —, never the reason, which the shipment keeps, so it
 * may cross a Messenger transport as it is.
 */
final readonly class ShipmentForced implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $shipmentId,
        public string $orderNumber,
        public string $actor,
        DateTimeImmutable $occurredAt,
    ) {
        $this->occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The moment the shipment was forced, in UTC.
     */
    #[Override]
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
