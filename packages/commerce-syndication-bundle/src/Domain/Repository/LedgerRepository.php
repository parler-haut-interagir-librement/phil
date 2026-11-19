<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Repository;

use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;

interface LedgerRepository
{
    /**
     * The entry of the order itself, not those of its returns.
     */
    public function findEntry(string $partner, string $orderNumber): ?LedgerEntry;

    /**
     * The entries of the order with the partner — the order's own and those of its returns —, oldest first.
     *
     * @return list<LedgerEntry>
     */
    public function entriesOf(string $partner, string $orderNumber): array;

    public function record(LedgerEntry $entry): void;

    /**
     * The entries with a partner not settled yet, oldest first.
     *
     * @return list<LedgerEntry>
     */
    public function openEntries(string $partner): array;
}
