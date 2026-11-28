<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Console;

use Phil\SitesConsoleBundle\Domain\Contract\JournalRepository;
use Phil\SitesConsoleBundle\Domain\Model\JournalEntry;
use Phil\SitesConsoleBundle\Domain\Status\JournalAction;
use Psr\Clock\ClockInterface;

/**
 * The journal of the console: every site registered or removed, every push and its delivery to each site, every
 * update plan and each of its steps.
 */
final readonly class Journal
{
    public function __construct(
        private JournalRepository $entries,
        private ClockInterface $clock,
    ) {
    }

    public function record(JournalAction $action, ?string $actor, ?string $siteId, ?string $reference, bool $succeeded, string $message): JournalEntry
    {
        $entry = JournalEntry::record($action, $actor, $siteId, $reference, $succeeded, $message, $this->clock->now());
        $this->entries->append($entry);

        return $entry;
    }

    /**
     * @return list<JournalEntry> the newest first
     */
    public function recent(int $limit, ?string $siteId = null): array
    {
        return $this->entries->recent(max(1, $limit), $siteId);
    }
}
