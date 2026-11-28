<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Memory;

use Override;
use Phil\SitesConsoleBundle\Domain\Contract\JournalRepository;
use Phil\SitesConsoleBundle\Domain\Model\JournalEntry;

/**
 * The journal in memory, for tests and demonstrations.
 */
final class InMemoryJournalRepository implements JournalRepository
{
    /** @var list<JournalEntry> in the order they were added */
    private array $entries = [];

    #[Override]
    public function append(JournalEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    #[Override]
    public function recent(int $limit, ?string $siteId = null): array
    {
        $entries = array_filter($this->entries, static fn (JournalEntry $entry): bool => null === $siteId || $entry->siteId() === $siteId);

        return \array_slice(array_reverse(array_values($entries)), 0, max(1, $limit));
    }
}
