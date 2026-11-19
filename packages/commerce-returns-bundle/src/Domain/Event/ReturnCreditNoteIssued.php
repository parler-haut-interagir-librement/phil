<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Event;

use DateTimeImmutable;
use Override;
use Phil\DddBundle\Domain\Event\DomainEvent;
use Phil\Money\Money;

/**
 * A credit note was issued for a return: its number, the return, the order and its total, for the accounts or the
 * mail that sends it. `ReturnCreditNotes` dispatches it by its class.
 */
final readonly class ReturnCreditNoteIssued implements DomainEvent
{
    public function __construct(
        public string $number,
        public string $returnId,
        public string $orderNumber,
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
