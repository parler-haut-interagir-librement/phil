<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\AiBundle\Domain\Journal\Decision;
use Phil\AiBundle\Domain\Journal\JournalTotals;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use PHPUnit\Framework\TestCase;

/**
 * One contract for every journal: what is saved is read back, decided and saved again, listed by subject and span,
 * and erased by subject.
 */
abstract class RepositoryTestCase extends TestCase
{
    public function testAnEntryIsReadBackAndDecided(): void
    {
        $journal = $this->repository();
        $journal->save($this->entry('t-1', 'project-1', '2026-11-06 10:00:00'));
        $this->clear();

        $entry = $journal->get('t-1');
        self::assertNotNull($entry);
        self::assertSame(['t-1', 'draft.rewrite', 'project-1', 'main', 'model-a', KeySource::User, 12, 5, 111, 'USD', 250, Decision::Pending, null], [
            $entry->id(), $entry->kind()->value, $entry->subject()->value, $entry->platform(), $entry->model(), $entry->keySource(),
            $entry->tokens()->input, $entry->tokens()->output, $entry->cost()->micros, $entry->cost()->currency, $entry->durationMs(), $entry->decision(), $entry->decidedAt(),
        ]);
        self::assertSame('2026-11-06 10:00:00', $entry->startedAt()->format('Y-m-d H:i:s'));
        self::assertSame(Output::Text, $entry->output());
        self::assertNull($journal->get('t-2'));

        $entry->decide(Decision::Rejected, new DateTimeImmutable('2026-11-06 10:05:00 UTC'));
        $journal->save($entry);
        $this->clear();

        $decided = $journal->get('t-1');
        self::assertNotNull($decided);
        self::assertSame(Decision::Rejected, $decided->decision());
        self::assertSame('2026-11-06 10:05:00', $decided->decidedAt()?->format('Y-m-d H:i:s'));
    }

    public function testEntriesAreListedBySubjectAndSpanOldestFirst(): void
    {
        $journal = $this->repository();
        $journal->save($this->entry('t-3', 'project-1', '2026-11-06 12:00:00'));
        $journal->save($this->entry('a-2', 'project-1', '2026-11-06 11:00:00'));
        $journal->save($this->entry('b-1', 'project-1', '2026-11-06 10:00:00'));
        $journal->save($this->entry('t-0', 'project-1', '2026-11-06 09:59:59'));
        $journal->save($this->entry('c-3', 'project-1', '2026-11-06 11:00:00'));
        $journal->save($this->entry('t-4', 'project-2', '2026-11-06 11:00:00'));
        $this->clear();

        $entries = $journal->ofSubject(Subject::fromString('project-1'), new DateTimeImmutable('2026-11-06 10:00:00 UTC'), new DateTimeImmutable('2026-11-06 12:00:00 UTC'));

        self::assertSame(['b-1', 'a-2', 'c-3'], array_map(static fn (JournalEntry $entry): string => $entry->id(), $entries));
        self::assertSame([0, 1, 2], array_keys($entries));
        self::assertSame([], $journal->ofSubject(Subject::fromString('project-3'), new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC')));
    }

    public function testTheTotalsAreSummedBySubjectAndSpan(): void
    {
        $journal = $this->repository();
        $journal->save($this->entry('t-1', 'project-1', '2026-11-06 10:00:00'));

        $decided = $this->entry('t-2', 'project-1', '2026-11-06 10:30:00');
        $decided->decide(Decision::Edited, new DateTimeImmutable('2026-11-06 10:31:00 UTC'));

        $journal->save($decided);
        $journal->save(JournalEntry::succeeded('t-3', TaskKind::fromString('cover.draw'), Subject::fromString('project-1'), 'images', 'model-b', KeySource::Application, new Tokens(0, 0), new Cost(40000, 'CAD'), new DateTimeImmutable('2026-11-06 11:00:00 UTC'), 900, Output::Image));
        $journal->save(JournalEntry::failed('t-4', TaskKind::fromString('draft.rewrite'), Subject::fromString('project-1'), 'main', 'model-a', KeySource::Application, 'USD', new DateTimeImmutable('2026-11-06 11:30:00 UTC'), 30));
        $journal->save(JournalEntry::failed('t-5', TaskKind::fromString('draft.rewrite'), Subject::fromString('project-1'), 'main', 'model-a', KeySource::Application, 'USD', new DateTimeImmutable('2026-11-06 11:40:00 UTC'), 30));
        $journal->save($this->entry('t-6', 'project-1', '2026-11-06 12:00:00'));
        $journal->save($this->entry('t-7', 'project-2', '2026-11-06 10:00:00'));
        $this->clear();

        $totals = $journal->totals(Subject::fromString('project-1'), new DateTimeImmutable('2026-11-06 10:00:00 UTC'), new DateTimeImmutable('2026-11-06 12:00:00 UTC'));

        self::assertSame([5, 2, 24, 10, ['CAD' => 40000, 'USD' => 222]], [$totals->tasks, $totals->failed, $totals->tokens->input, $totals->tokens->output, $this->sorted($totals->costs)]);
        self::assertSame(['pending' => 2, 'accepted' => 0, 'edited' => 1, 'rejected' => 0], $totals->decisions);
        self::assertEquals(JournalTotals::of(), $journal->totals(Subject::fromString('project-3'), new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC')));
    }

    public function testASubjectIsErased(): void
    {
        $journal = $this->repository();
        $journal->save($this->entry('t-1', 'project-1', '2026-11-06 10:00:00'));
        $journal->save($this->entry('t-2', 'project-2', '2026-11-06 10:00:00'));
        $journal->save($this->entry('t-3', 'project-1', '2026-11-06 11:00:00'));
        $this->clear();

        self::assertSame(2, $journal->eraseSubject(Subject::fromString('project-1')));
        $this->clear();

        self::assertSame([null, 't-2', null], [$journal->get('t-1'), $journal->get('t-2')?->id(), $journal->get('t-3')]);
        self::assertSame(0, $journal->eraseSubject(Subject::fromString('project-1')));
    }

    abstract protected function repository(): JournalRepository;

    /**
     * @param array<string, int> $costs
     *
     * @return array<string, int>
     */
    private function sorted(array $costs): array
    {
        ksort($costs);

        return $costs;
    }

    abstract protected function clear(): void;

    private function entry(string $id, string $subject, string $startedAt): JournalEntry
    {
        return JournalEntry::succeeded($id, TaskKind::fromString('draft.rewrite'), Subject::fromString($subject), 'main', 'model-a', KeySource::User, new Tokens(12, 5), new Cost(111, 'USD'), new DateTimeImmutable($startedAt.' UTC'), 250);
    }
}
