<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Contract;

use Phil\SitesConsoleBundle\Domain\Model\JournalEntry;

/**
 * The journal, only ever added to.
 */
interface JournalRepository
{
    public function append(JournalEntry $entry): void;

    /**
     * @param ?string $siteId the lines of one site, or every line
     *
     * @return list<JournalEntry> the newest first
     */
    public function recent(int $limit, ?string $siteId = null): array;
}
