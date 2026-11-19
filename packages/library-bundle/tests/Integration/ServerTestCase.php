<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Override;
use Phil\LibraryBundle\Application\Search\SearchCriteria;
use Phil\LibraryBundle\Application\Search\TitleRow;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Model\Title;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Domain\Status\LoanStatus;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use Phil\LibraryBundle\Infrastructure\Doctrine\DbalTitleSearch;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The bundle's mapping, queries and lock on a real server, read from the environment: the tables created from the
 * mapping, what is kept read back in UTC, the search and its counts, each query of the loans, the bulk changes of the
 * history, the cascade of a title removed, the unique barcode, and two loans of one copy made at once.
 */
abstract class ServerTestCase extends TestCase
{
    private Server $server;

    private MockClock $clock;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-13 10:00:00', 'America/Toronto');
        $this->server = Server::connect($this->url(), $this->clock);
        $this->server->recreate();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->server)) {
            $this->server->entityManager->getConnection()->close();
        }
    }

    public function testWhatIsKeptIsReadBackInUtc(): void
    {
        $record = new Record(TitleKind::Game, 'Azul', '9780306406157', 'Michael Kiesling', 'Plan B', 2017, 2, 4, 30, 45, 8, 'en-CA', "Tiles.\nOn a board.");
        $title = $this->server->catalogue()->add($record);
        $copy = $this->server->catalogue()->addCopy($title->id, 'G-1', CopyCondition::Worn, 'item-1');
        $loan = $this->server->service()->lend('G-1', 'card-7');
        $this->server->entityManager->clear();

        $read = $this->server->catalogue()->title($title->id);
        self::assertEquals($record, $read->record());
        $readCopy = $this->server->catalogue()->copy($copy->id);
        self::assertSame(['G-1', CopyCondition::Worn, 'item-1', 1], [$readCopy->barcode(), $readCopy->condition(), $readCopy->stockroomItem(), $readCopy->claims()]);
        $readLoan = $this->server->loans()->get($loan->id);
        self::assertInstanceOf(Loan::class, $readLoan);
        self::assertSame([LoanStatus::Active, 'card-7', '2026-11-28T04:59:59+00:00'], [$readLoan->status(), $readLoan->member(), $readLoan->dueAt()->setTimezone(new DateTimeZone('UTC'))->format(\DATE_ATOM)]);
        self::assertSame('2026-11-13 15:00:00', $this->server->entityManager->getConnection()->fetchOne('SELECT starts_at FROM '.Loan::TABLE));
    }

    public function testTheSearchFiltersCountsAndPages(): void
    {
        $catalogue = $this->server->catalogue();
        $azul = $catalogue->add(new Record(TitleKind::Game, 'Azul', '9780306406157', 'Michael Kiesling', minPlayers: 2, maxPlayers: 4, minMinutes: 30, maxMinutes: 45, minAge: 8, language: 'en'));
        $catalogue->add(new Record(TitleKind::Game, 'Catan', creators: 'Klaus Teuber', minPlayers: 3, maxPlayers: 4, minMinutes: 60, minAge: 10));
        $catalogue->add(new Record(TitleKind::Book, 'Dune', creators: 'Frank Herbert', language: 'en'));
        $catalogue->add(new Record(TitleKind::Game, '100% Orange'));
        $catalogue->addCopy($azul->id, 'G-1');
        $catalogue->addCopy($azul->id, 'G-2');

        $withdrawn = $catalogue->addCopy($azul->id, 'G-3', CopyCondition::Withdrawn);
        $catan = $catalogue->addCopy($this->id('Catan'), 'G-4');
        $dune = $catalogue->addCopy($this->id('Dune'), 'B-1');
        $this->server->service()->lend('G-1', 'card-7');
        $this->server->service()->reserve($catan->id, 'card-8', $this->at('2026-11-14 10:00'), $this->at('2026-11-16 10:00'));
        $this->server->service()->reserve($dune->id, 'card-8', $this->at('2026-11-13 10:30'), $this->at('2026-11-16 10:00'));
        self::assertInstanceOf(Copy::class, $withdrawn);
        $search = $this->server->search();

        self::assertSame([['100% Orange', 0, 0], ['Azul', 2, 1], ['Catan', 1, 1], ['Dune', 1, 1]], $this->rows($search->search(new SearchCriteria(), 1, 10)->rows));
        $this->clock->modify('+1 hour');
        self::assertSame([['Dune', 1, 0]], $this->rows($search->search(new SearchCriteria('herbert'), 1, 10)->rows), 'a reservation begun keeps its copy');
        self::assertSame(['Azul', 'Catan'], $this->names($search->search(new SearchCriteria(availableOnly: true), 1, 10)->rows));
        self::assertSame(['Azul'], $this->names($search->search(new SearchCriteria('KIESL'), 1, 10)->rows));
        self::assertSame(['Azul'], $this->names($search->search(new SearchCriteria('0-306-40615-2'), 1, 10)->rows));
        self::assertSame(['100% Orange'], $this->names($search->search(new SearchCriteria('100%'), 1, 10)->rows));
        self::assertSame([], $this->names($search->search(new SearchCriteria('a_u'), 1, 10)->rows));
        self::assertSame([], $this->names($search->search(new SearchCriteria('!'), 1, 10)->rows));
        self::assertSame(['Dune'], $this->names($search->search(new SearchCriteria(kind: TitleKind::Book), 1, 10)->rows));
        self::assertSame(['100% Orange', 'Azul', 'Dune'], $this->names($search->search(new SearchCriteria(players: 2), 1, 10)->rows));
        self::assertSame(['100% Orange', 'Azul', 'Dune'], $this->names($search->search(new SearchCriteria(minutes: 45), 1, 10)->rows));
        self::assertSame(['100% Orange', 'Azul', 'Dune'], $this->names($search->search(new SearchCriteria(age: 9), 1, 10)->rows));
        self::assertSame(['Azul', 'Dune'], $this->names($search->search(new SearchCriteria(language: 'en'), 1, 10)->rows));

        $page = $search->search(new SearchCriteria(), 2, 3);
        self::assertSame([['Dune'], 4, 2, 3, 2], [$this->names($page->rows), $page->total, $page->page, $page->perPage, $page->pages()]);

        $row = $search->search(new SearchCriteria('azul'), 1, 1)->rows[0];
        self::assertSame([$azul->id, TitleKind::Game, 'Michael Kiesling', null, 2, 4, 30, 45, 8], [$row->id, $row->kind, $row->creators, $row->year, $row->minPlayers, $row->maxPlayers, $row->minMinutes, $row->maxMinutes, $row->minAge]);

        foreach ([[0, 10], [1001, 10], [1, 0], [1, 101]] as [$number, $size]) {
            try {
                $search->search(new SearchCriteria(), $number, $size);
                self::fail('A page out of bounds was read.');
            } catch (InvalidLibraryData $invalid) {
                self::assertSame('A search reads pages 1 to 1000 of 1 to 100 titles.', $invalid->getMessage());
            }
        }

        self::assertSame([], $search->search(new SearchCriteria(), DbalTitleSearch::MAX_PAGE, DbalTitleSearch::MAX_PER_PAGE)->rows);
    }

    public function testEachQueryOfTheLoans(): void
    {
        $catalogue = $this->server->catalogue();
        $title = $catalogue->add(new Record(TitleKind::Game, 'Azul'));
        $first = $catalogue->addCopy($title->id, 'G-1');
        $catalogue->addCopy($title->id, 'G-2');
        $service = $this->server->service();
        $later = $service->reserve($first->id, 'card-7', $this->at('2026-12-01 10:00'), $this->at('2026-12-03 10:00'));
        $out = $service->lend('G-1', 'card-7', $this->at('2026-11-20 12:00'));
        $other = $service->lend('G-2', 'card-8');
        $loans = $this->server->loans();
        $this->server->entityManager->clear();
        $copy = $this->server->copies()->get($first->id);
        self::assertInstanceOf(Copy::class, $copy);

        self::assertSame([$out->id, $later->id], $this->ids($loans->holding($copy)));
        self::assertSame($out->id, $loans->activeOf($copy)?->id);
        self::assertSame([$later->id, $out->id], $this->ids($loans->ofMember('card-7', 10)));
        self::assertSame([$later->id], $this->ids($loans->ofMember('card-7', 1)));
        self::assertSame(2, $loans->countOpen('card-7'));
        self::assertSame(0, $loans->countOpen('card-9'));
        self::assertFalse($loans->hasOverdue('card-7', $this->at('2026-11-20 11:59')));
        self::assertTrue($loans->hasOverdue('card-7', $this->at('2026-11-20 12:00')));
        self::assertSame([$out->id], $this->ids($loans->dueBefore($this->at('2026-11-21'), 10)));
        self::assertSame([$out->id, $other->id], $this->ids($loans->dueBefore($this->at('2026-12-31'), 10)));
        self::assertSame([$out->id], $this->ids($loans->dueBefore($this->at('2026-12-31'), 1)));
        self::assertSame(['G-1', 'G-2'], array_map(static fn (Copy $copy): string => $copy->barcode(), $this->server->copies()->ofTitle($copy->title(), 10)));
        self::assertSame($first->id, $this->server->copies()->byBarcode('G-1')?->id);

        $this->clock->modify('2026-11-19 12:00');
        $service->giveBack('G-1');
        $this->server->entityManager->clear();
        self::assertSame([$later->id], $this->ids($loans->ofMember('card-7', 10, true)));
        self::assertNull($loans->activeOf($copy));

        self::assertSame(0, $loans->forgetClosedBefore($this->at('2026-11-19 12:00')));
        self::assertSame(1, $loans->forgetClosedBefore($this->at('2026-11-19 12:01')));
        self::assertSame(0, $loans->forgetClosedBefore($this->at('2026-12-31')), 'forgotten once');
        self::assertSame(1, $loans->forgetMember('card-7'));
        self::assertSame(0, $loans->countOpen('card-7'));
        self::assertSame('card-8', $loans->get($other->id)?->member());
        self::assertNull($loans->get($later->id)?->member());
    }

    public function testABulkChangeDoesNotLeaveStaleLoans(): void
    {
        $title = $this->server->catalogue()->add(new Record(TitleKind::Game, 'Azul'));
        $this->server->catalogue()->addCopy($title->id, 'G-1');
        $loan = $this->server->service()->lend('G-1', 'card-7');
        $this->server->loans()->forgetMember('card-7');

        $renewed = $this->server->service()->renew($loan->id);
        self::assertNull($renewed->member());
        self::assertSame(1, $renewed->renewals());
    }

    public function testRemovingATitleRemovesItsCopiesAndLoans(): void
    {
        $title = $this->server->catalogue()->add(new Record(TitleKind::Game, 'Azul'));
        $this->server->catalogue()->addCopy($title->id, 'G-1');
        $this->server->service()->lend('G-1', 'card-7');
        $connection = $this->server->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM '.Title::TABLE.' WHERE id = ?', [$title->id]);

        self::assertSame([0, 0], [$connection->fetchOne('SELECT COUNT(*) FROM '.Copy::TABLE), $connection->fetchOne('SELECT COUNT(*) FROM '.Loan::TABLE)]);
    }

    public function testABarcodeIsUniqueInTheDatabase(): void
    {
        $title = $this->server->catalogue()->add(new Record(TitleKind::Game, 'Azul'));
        $copy = Copy::of($title, 'G-1', $this->clock->now());
        $this->server->copies()->save($copy);
        $twin = Copy::of($title, 'G-1', $this->clock->now());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->server->copies()->save($twin);
    }

    public function testTwoLoansOfOneCopyMadeAtOnceAreNotBothKept(): void
    {
        $title = $this->server->catalogue()->add(new Record(TitleKind::Game, 'Azul'));
        $this->server->catalogue()->addCopy($title->id, 'G-1');
        $other = Server::connect($this->url(), $this->clock);
        $theirs = $other->copies()->byBarcode('G-1');
        self::assertInstanceOf(Copy::class, $theirs);
        $this->server->service()->lend('G-1', 'card-7');

        // What Loans does: the loan claims its copy before it is kept.
        $theirs->claim();

        try {
            $other->loans()->save(Loan::lend($theirs, 'card-8', $this->at('2026-11-20'), $this->clock->now()));
            self::fail('A loan made from an older read was kept.');
        } catch (OptimisticLockException) {
        } finally {
            $other->entityManager->getConnection()->close();
        }

        self::assertSame([1, 'card-7'], [$this->server->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM '.Loan::TABLE), $this->server->entityManager->getConnection()->fetchOne('SELECT member FROM '.Loan::TABLE)]);
    }

    private function url(): string
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return $url;
    }

    private function id(string $name): string
    {
        $id = $this->server->entityManager->getConnection()->fetchOne('SELECT id FROM '.Title::TABLE.' WHERE name = ?', [$name]);
        self::assertIsString($id);

        return trim($id);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    /**
     * @param list<TitleRow> $rows
     *
     * @return list<array{string, int, int}>
     */
    private function rows(array $rows): array
    {
        return array_map(static fn (TitleRow $row): array => [$row->name, $row->copies, $row->available], $rows);
    }

    /**
     * @param list<TitleRow> $rows
     *
     * @return list<string>
     */
    private function names(array $rows): array
    {
        return array_map(static fn (TitleRow $row): string => $row->name, $rows);
    }

    /**
     * @param list<Loan> $loans
     *
     * @return list<string>
     */
    private function ids(array $loans): array
    {
        return array_map(static fn (Loan $loan): string => $loan->id, $loans);
    }
}
