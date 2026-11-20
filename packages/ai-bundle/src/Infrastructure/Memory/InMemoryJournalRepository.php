<?php

declare(strict_types=1);

namespace Phil\AiBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\AiBundle\Domain\Journal\JournalTotals;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\Domain\ValueObject\Subject;

/**
 * The journal in an array, for tests: it keeps copies, as a database does, so an entry changed and not saved stays
 * as it was.
 */
final class InMemoryJournalRepository implements JournalRepository
{
    /** @var array<string, JournalEntry> */
    private array $entries = [];

    #[Override]
    public function get(string $id): ?JournalEntry
    {
        $entry = $this->entries[$id] ?? null;

        return null === $entry ? null : clone $entry;
    }

    #[Override]
    public function save(JournalEntry $entry): void
    {
        $this->entries[$entry->id()] = clone $entry;
    }

    #[Override]
    public function ofSubject(Subject $subject, DateTimeImmutable $from, DateTimeImmutable $before): array
    {
        $entries = array_filter(
            $this->entries,
            static fn (JournalEntry $entry): bool => $entry->subject()->value === $subject->value && $entry->startedAt() >= $from && $entry->startedAt() < $before,
        );
        usort($entries, static fn (JournalEntry $a, JournalEntry $b): int => [$a->startedAt(), $a->id()] <=> [$b->startedAt(), $b->id()]);

        return array_map(static fn (JournalEntry $entry): JournalEntry => clone $entry, $entries);
    }

    #[Override]
    public function totals(Subject $subject, DateTimeImmutable $from, DateTimeImmutable $before): JournalTotals
    {
        return JournalTotals::of(...$this->ofSubject($subject, $from, $before));
    }

    #[Override]
    public function eraseSubject(Subject $subject): int
    {
        $count = \count($this->entries);
        $this->entries = array_filter($this->entries, static fn (JournalEntry $entry): bool => $entry->subject()->value !== $subject->value);

        return $count - \count($this->entries);
    }
}
