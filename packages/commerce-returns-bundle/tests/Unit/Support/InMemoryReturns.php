<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnCreditNote;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Repository\ReturnRepository;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;

/**
 * Returns, in memory.
 */
final class InMemoryReturns implements ReturnRepository
{
    /** @var array<string, ReturnRequest> */
    public array $returns = [];

    /** @var array<string, ReturnCreditNote> */
    public array $creditNotes = [];

    #[Override]
    public function findReturn(string $id): ?ReturnRequest
    {
        return $this->returns[$id] ?? null;
    }

    #[Override]
    public function addReturn(ReturnRequest $return): void
    {
        $this->returns[$return->id] = $return;
    }

    #[Override]
    public function returnsOf(string $orderNumber): array
    {
        return array_values(array_filter($this->returns, static fn (ReturnRequest $return): bool => $return->orderNumber === $orderNumber));
    }

    #[Override]
    public function authorizedBefore(DateTimeImmutable $before): array
    {
        $found = array_values(array_filter($this->returns, static fn (ReturnRequest $return): bool => ReturnState::Authorized === $return->state() && $return->changedAt() < $before));
        usort($found, static fn (ReturnRequest $left, ReturnRequest $right): int => $left->changedAt() <=> $right->changedAt());

        return $found;
    }

    #[Override]
    public function findCreditNote(string $returnId): ?ReturnCreditNote
    {
        return array_find($this->creditNotes, static fn (ReturnCreditNote $note): bool => $note->returnId === $returnId);
    }

    #[Override]
    public function addCreditNote(ReturnCreditNote $note): void
    {
        $this->creditNotes[$note->number] = $note;
    }
}
