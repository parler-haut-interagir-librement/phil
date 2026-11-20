<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Event;

use DateTimeImmutable;
use Override;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Phil\DddBundle\Domain\Event\DomainEvent;

/**
 * A return moved: requested — from no state —, authorized, sent back for information, received, resolved, rejected,
 * cancelled or expired. It carries identifiers only; a listener — the stock, a notification, the accounts — reads
 * the return itself from its store. `Returns` dispatches it by its class.
 */
final readonly class ReturnStateChanged implements DomainEvent
{
    public function __construct(
        public string $returnId,
        public string $orderNumber,
        public ?ReturnState $from,
        public ReturnState $to,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    #[Override]
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
