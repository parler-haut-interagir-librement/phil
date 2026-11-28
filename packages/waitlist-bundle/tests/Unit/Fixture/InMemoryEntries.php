<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\WaitlistBundle\Domain\Contract\EntryRepository;
use Phil\WaitlistBundle\Domain\Model\Entry;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;

/**
 * The entries in an array, read by rank; it records the limits it was asked for and the entries refreshed.
 */
final class InMemoryEntries implements EntryRepository
{
    /** @var array<string, Entry> */
    public array $entries = [];

    /** @var list<int> */
    public array $limits = [];

    /** @var list<string> */
    public array $refreshed = [];

    /** @var list<string> the entries saved, by identifier, and whether inside the lock */
    public array $saved = [];

    public function __construct(
        private readonly ?InMemoryWaitlists $waitlists = null,
    ) {
    }

    #[Override]
    public function get(string $id): ?Entry
    {
        return $this->entries[$id] ?? null;
    }

    #[Override]
    public function save(Entry $entry): void
    {
        $this->entries[$entry->id] = $entry;
        $this->saved[] = $entry->id.(true === $this->waitlists?->inside ? ' locked' : ' unlocked');
    }

    #[Override]
    public function refresh(Entry $entry): void
    {
        $this->refreshed[] = $entry->id;
    }

    #[Override]
    public function live(Waitlist $waitlist, string $participant): ?Entry
    {
        foreach ($this->ofWaitlist($waitlist, \PHP_INT_MAX) as $entry) {
            if ($entry->participant() === $participant && $entry->status()->isLive()) {
                return $entry;
            }
        }

        return null;
    }

    #[Override]
    public function waiting(Waitlist $waitlist, int $limit): array
    {
        $this->limits[] = $limit;

        return $this->ofWaitlist($waitlist, $limit, EntryStatus::Waiting);
    }

    #[Override]
    public function queued(Waitlist $waitlist, int $limit): array
    {
        $this->limits[] = $limit;
        $queued = array_filter($this->ofWaitlist($waitlist, \PHP_INT_MAX, EntryStatus::Waiting), static fn (Entry $entry): bool => str_starts_with($entry->rank(), Entry::QUEUED));

        return \array_slice(array_values($queued), 0, $limit);
    }

    #[Override]
    public function ahead(Entry $entry): int
    {
        return \count(array_filter($this->ofWaitlist($entry->waitlist(), \PHP_INT_MAX, EntryStatus::Waiting), static fn (Entry $other): bool => $other->rank() < $entry->rank()));
    }

    #[Override]
    public function ofWaitlist(Waitlist $waitlist, int $limit, ?EntryStatus $status = null): array
    {
        $found = array_values(array_filter($this->entries, static fn (Entry $entry): bool => $entry->waitlist() === $waitlist && (!$status instanceof EntryStatus || $entry->status() === $status)));
        usort($found, static fn (Entry $a, Entry $b): int => $a->rank() <=> $b->rank());

        return \array_slice($found, 0, $limit);
    }

    #[Override]
    public function places(Waitlist $waitlist, EntryStatus $status): int
    {
        return array_sum(array_map(static fn (Entry $entry): int => $entry->places(), $this->ofWaitlist($waitlist, \PHP_INT_MAX, $status)));
    }

    #[Override]
    public function expired(Waitlist $waitlist, DateTimeImmutable $now, int $limit): array
    {
        $this->limits[] = $limit;
        $expired = array_filter($this->ofWaitlist($waitlist, \PHP_INT_MAX, EntryStatus::Offered), static fn (Entry $entry): bool => $entry->hasExpiredAt($now));

        return \array_slice(array_values($expired), 0, $limit);
    }

    #[Override]
    public function waitlistsWithExpiredOffers(DateTimeImmutable $now, int $limit): array
    {
        $this->limits[] = $limit;
        $ids = [];

        foreach ($this->entries as $entry) {
            if ($entry->hasExpiredAt($now)) {
                $ids[$entry->waitlist()->id] = true;
            }
        }

        return \array_slice(array_keys($ids), 0, $limit);
    }
}
