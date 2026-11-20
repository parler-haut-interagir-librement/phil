<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Event;

use DateTimeImmutable;
use Override;
use Phil\CommerceReturnsBundle\Domain\Rma\Resolution;
use Phil\DddBundle\Domain\Event\DomainEvent;
use Phil\Money\Money;

/**
 * A return was settled — refunded, credited or exchanged — for its value: what the accounts, a loyalty programme or
 * a notification listen to. `Returns` dispatches it by its class, after the `ReturnStateChanged` of its resolution.
 */
final readonly class ReturnSettled implements DomainEvent
{
    public function __construct(
        public string $returnId,
        public string $orderNumber,
        public Resolution $resolution,
        public Money $value,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    #[Override]
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
