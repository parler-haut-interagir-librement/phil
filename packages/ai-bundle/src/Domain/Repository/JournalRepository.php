<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\AiBundle\Domain\Journal\JournalTotals;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\ValueObject\Subject;

/**
 * The journal of tasks.
 */
interface JournalRepository
{
    public function get(string $id): ?JournalEntry;

    /**
     * Writes the entry, new or decided since it was read.
     */
    public function save(JournalEntry $entry): void;

    /**
     * The subject's entries started from `$from` included to `$before` excluded, oldest first.
     *
     * @return list<JournalEntry>
     */
    public function ofSubject(Subject $subject, DateTimeImmutable $from, DateTimeImmutable $before): array;

    /**
     * What the subject's entries started from `$from` included to `$before` excluded add up to, summed by the store
     * rather than read entry by entry.
     */
    public function totals(Subject $subject, DateTimeImmutable $from, DateTimeImmutable $before): JournalTotals;

    /**
     * Forgets every entry of the subject, when the account or project it names is erased.
     *
     * @return int how many entries were forgotten
     */
    public function eraseSubject(Subject $subject): int;
}
