<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\AiBundle\Domain\Journal\Decision;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use Phil\AiBundle\Infrastructure\Memory\InMemoryJournalRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryJournalRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    public function testItKeepsCopies(): void
    {
        $journal = $this->repository();
        $entry = JournalEntry::succeeded('t-1', TaskKind::fromString('k'), Subject::fromString('p'), 'main', 'm', KeySource::Application, Tokens::none(), new Cost(0, 'USD'), new DateTimeImmutable('2026-11-06 UTC'), 1);
        $journal->save($entry);

        $entry->decide(Decision::Accepted, new DateTimeImmutable());
        self::assertSame(Decision::Pending, $this->read($journal, 't-1')->decision(), 'An entry changed after its save.');

        $this->read($journal, 't-1')->decide(Decision::Rejected, new DateTimeImmutable());
        self::assertSame(Decision::Pending, $this->read($journal, 't-1')->decision(), 'An entry read and changed, never saved.');

        $journal->ofSubject(Subject::fromString('p'), new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC'))[0]->decide(Decision::Rejected, new DateTimeImmutable());
        self::assertSame(Decision::Pending, $this->read($journal, 't-1')->decision(), 'An entry listed and changed, never saved.');
    }

    private function read(JournalRepository $journal, string $id): JournalEntry
    {
        $entry = $journal->get($id);
        self::assertNotNull($entry);

        return $entry;
    }

    #[Override]
    protected function repository(): JournalRepository
    {
        return new InMemoryJournalRepository();
    }

    #[Override]
    protected function clear(): void
    {
    }
}
