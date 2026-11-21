<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Event;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\DddBundle\Domain\Event\DomainEvent;

/**
 * Units of an order left the shelf: what a ledger of owners, a sales report or a notification listens to, without the
 * logistics knowing them. It carries the order's number and, per SKU and location, the units that left, and nothing
 * about the buyer, so it may cross a Messenger transport as it is. `Stock` dispatches it by its class, once per call to
 * `ship()` or `shipPart()` that moved units, after their movements are written to the journal.
 */
final readonly class UnitsShipped implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    /**
     * @param non-empty-list<ShippedLine> $lines one per SKU and location, in the order the units left
     */
    public function __construct(
        public string $orderNumber,
        public array $lines,
        DateTimeImmutable $occurredAt,
    ) {
        $this->occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The moment the units left, in UTC.
     */
    #[Override]
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Every unit that left, over the lines.
     */
    public function units(): int
    {
        return array_sum(array_map(static fn (ShippedLine $line): int => $line->units, $this->lines));
    }
}
