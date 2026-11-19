<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnCreditNote;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;

interface ReturnRepository
{
    public function findReturn(string $id): ?ReturnRequest;

    public function addReturn(ReturnRequest $return): void;

    /**
     * @return list<ReturnRequest> every return of the order, oldest first
     */
    public function returnsOf(string $orderNumber): array;

    /**
     * @return list<ReturnRequest> the authorized returns that last moved before that moment, oldest first
     */
    public function authorizedBefore(DateTimeImmutable $before): array;

    public function findCreditNote(string $returnId): ?ReturnCreditNote;

    public function addCreditNote(ReturnCreditNote $note): void;
}
