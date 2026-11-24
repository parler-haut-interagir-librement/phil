<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Infrastructure;

use DateTimeZone;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceConsignmentBundle\Application\ConsignorAgreements;
use Phil\CommerceConsignmentBundle\Application\ConsignorPayouts;
use Phil\CommerceConsignmentBundle\Application\ConsignorStatements;
use Phil\CommerceConsignmentBundle\Domain\Contract\ConsignorRepository;
use Phil\CommerceConsignmentBundle\Domain\Contract\LedgerRepository;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineConsignorRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineLedgerRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineStatementNumbering;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Currency;

/**
 * The Doctrine repository on a connection, its tables dropped and created from the mapping, with the binary keys of
 * MySQL.
 */
abstract class DoctrineRepositoryTestCase extends RepositoryTestCase
{
    /**
     * Whether the test opens a second connection — a second request — on the tables of the first.
     */
    protected bool $shared = false;

    private ?EntityManager $entityManager = null;

    #[Override]
    protected function tearDown(): void
    {
        $this->entityManager?->getConnection()->close();
        $this->entityManager = null;
    }

    abstract protected function connection(): Connection;

    protected function entityManager(): EntityManager
    {
        if (!$this->entityManager instanceof EntityManager) {
            $this->entityManager = $this->newEntityManager();
            $tool = new SchemaTool($this->entityManager);
            $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }

        return $this->entityManager;
    }

    #[Override]
    protected function repository(): LedgerRepository
    {
        return new DoctrineLedgerRepository($this->entityManager());
    }

    #[Override]
    protected function consignors(): ConsignorRepository
    {
        return new DoctrineConsignorRepository($this->entityManager());
    }

    /**
     * Another request: an entity manager of its own, on a connection of its own, to the same tables.
     */
    protected function newEntityManager(): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $events = new EventManager();
        $events->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());

        return new EntityManager($this->connection(), $configuration, $events);
    }

    public function testTwoRequestsCannotWriteThePayoutOfOneKey(): void
    {
        $this->shared = true;
        $first = $this->entityManager();
        new Ledger(new DoctrineLedgerRepository($first))->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        new Ledger(new DoctrineLedgerRepository($first))->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 10, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(10));
        $first->flush();
        $second = $this->newEntityManager();
        $author = Owner::consignor(Fixtures::AUTHOR);

        // Both requests find no payout of the key, and both write theirs.
        new ConsignorPayouts(new DoctrineLedgerRepository($first), new DoctrineConsignorRepository($first))->record('PAY-1', $author, Fixtures::cad(1000), Fixtures::at(11), 'cash', null, Fixtures::at(11));
        new ConsignorPayouts(new DoctrineLedgerRepository($second), new DoctrineConsignorRepository($second))->record('PAY-1', $author, Fixtures::cad(1000), Fixtures::at(11), 'cash', null, Fixtures::at(11));
        $first->flush();

        try {
            $second->flush();
            self::fail('Two payouts of one key were written.');
        } catch (UniqueConstraintViolationException) {
            $second->getConnection()->close();
        }

        $first->clear();
        self::assertSame(1000, new DoctrineConsignorRepository($first)->paidTo(Fixtures::AUTHOR, 'CAD'));
    }

    public function testTwoRequestsCannotReverseOnePayoutTwice(): void
    {
        $this->shared = true;
        $first = $this->entityManager();
        new Ledger(new DoctrineLedgerRepository($first))->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        new Ledger(new DoctrineLedgerRepository($first))->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 10, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(10));
        $payout = new ConsignorPayouts(new DoctrineLedgerRepository($first), new DoctrineConsignorRepository($first))->record('PAY-1', Owner::consignor(Fixtures::AUTHOR), Fixtures::cad(1000), Fixtures::at(11), 'cash', null, Fixtures::at(11));
        $first->flush();
        $second = $this->newEntityManager();

        new ConsignorPayouts(new DoctrineLedgerRepository($first), new DoctrineConsignorRepository($first))->reverse($payout->id, 'R-1', Fixtures::at(12));
        new ConsignorPayouts(new DoctrineLedgerRepository($second), new DoctrineConsignorRepository($second))->reverse($payout->id, 'R-2', Fixtures::at(12));
        $first->flush();

        $this->expectException(UniqueConstraintViolationException::class);

        try {
            $second->flush();
        } finally {
            $second->getConnection()->close();
        }
    }

    public function testEachLookupLoadsItsOwnRows(): void
    {
        $entityManager = $this->entityManager();
        $ledger = new Ledger(new DoctrineLedgerRepository($entityManager));
        // Written first, received last: the identifiers and the instants are in opposite orders.
        [$new] = $ledger->receive(Fixtures::consigned(1, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-NEW'), Fixtures::at(10));
        $ledger->receive(Fixtures::consigned(1, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-OLD'), Fixtures::at(9));
        [$pen] = $ledger->receive(Receipt::of('CS-PEN', Fixtures::SHELF, [ReceiptLine::consigned('PEN', 1, Fixtures::AUTHOR, ConsignmentTerms::commission(1))]), Fixtures::at(11));
        $ledger->receive(Fixtures::consigned(4, 'artisan-bo', ConsignmentTerms::commission(1), 'CS-ART'), Fixtures::at(8));
        $ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(2000), IssueOrder::OldestFirst, Fixtures::at(12));
        $ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(2000), IssueOrder::ConsignedFirst, Fixtures::at(11, 30));

        $entityManager->flush();

        $fresh = static function () use ($entityManager): DoctrineLedgerRepository {
            $entityManager->clear();

            return new DoctrineLedgerRepository($entityManager);
        };
        $hours = static fn (array $entries): array => array_map(static fn (LedgerEntry $entry): string => $entry->movedAt()->setTimezone(new DateTimeZone('America/Toronto'))->format('H:i'), $entries);
        $references = static fn (array $layers): array => array_map(static fn (StockLayer $layer): ?string => $layer->reference, $layers);

        self::assertSame('CS-NEW', $fresh()->latestLayer(Fixtures::BOOK, Fixtures::AUTHOR)?->reference);
        self::assertSame(['CS-NEW'], $references($fresh()->layersFrom($new->origin)));
        self::assertSame(['11:30', '12:00'], $hours($fresh()->entriesOf('ORD-1', Fixtures::BOOK)));
        self::assertSame(['08:00', '09:00', '10:00', '11:30', '12:00'], $hours($fresh()->journal(Fixtures::BOOK)));
        self::assertSame(['CS-OLD'], $references($fresh()->consignedLayers(Fixtures::AUTHOR, 1)));
        self::assertCount(1, $fresh()->consignedLayers(null, 0));

        $repository = $fresh();
        $repository->layer($pen->id);
        self::assertSame(['CS-OLD', 'CS-NEW', 'CS-PEN'], $references($repository->consignedLayers(Fixtures::AUTHOR, 10)));

        // A layer emptied in this request, not flushed yet, holds nothing.
        $repository = $fresh();
        new Ledger($repository)->giveBack('RS-1', Owner::consignor(Fixtures::AUTHOR), Fixtures::SHELF, [Fixtures::BOOK => 2], Fixtures::at(13));
        self::assertSame(['CS-PEN'], $references($repository->consignedLayers(Fixtures::AUTHOR, 10)));
        self::assertSame(['CS-ART'], $references($repository->layersOf(Fixtures::BOOK)));
    }

    public function testWhatIsNotFlushedYetCountsWithinTheBounds(): void
    {
        $entityManager = $this->entityManager();
        [$first] = new Ledger(new DoctrineLedgerRepository($entityManager))->receive(Fixtures::consigned(5, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-1'), Fixtures::utc('2026-10-01'));
        new Ledger(new DoctrineLedgerRepository($entityManager))->sell('ORD-A', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(1000), IssueOrder::OldestFirst, Fixtures::utc('2026-10-15'));
        $entityManager->flush();
        $entityManager->clear();

        $repository = new DoctrineLedgerRepository($entityManager);
        $ledger = new Ledger($repository);
        [$second] = $ledger->receive(Fixtures::consigned(2, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-2', Fixtures::BACK), Fixtures::utc('2026-10-10'));
        $ledger->sell('ORD-B', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(1000), IssueOrder::OldestFirst, Fixtures::utc('2026-10-12'));
        $ledger->sell('ORD-C', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(1000), IssueOrder::OldestFirst, Fixtures::utc('2026-10-20'));
        $ledger->receive(Fixtures::consigned(1, 'artisan-bo', ConsignmentTerms::commission(1), 'CS-3'), Fixtures::utc('2026-10-15'));

        $entries = $repository->consignorEntries(Fixtures::AUTHOR, Fixtures::utc('2026-10-10'), Fixtures::utc('2026-10-20'), 10);
        self::assertSame(['CS-2', 'ORD-B', 'ORD-A'], array_map(static fn (LedgerEntry $entry): ?string => $entry->reference, $entries));

        $held = $repository->heldBy(Fixtures::AUTHOR, Fixtures::utc('2026-10-20'), 10);
        ksort($held);
        $expected = [$first->id => 3, $second->id => 2];
        ksort($expected);
        self::assertSame($expected, $held);
    }

    public function testStatementNumbersFollowEachOtherInTheirSeries(): void
    {
        $entityManager = $this->entityManager();
        $numbering = new DoctrineStatementNumbering($entityManager);
        $consignors = new DoctrineConsignorRepository($entityManager);
        $statements = new ConsignorStatements(new DoctrineLedgerRepository($entityManager), $consignors, $numbering, new ConsignorAgreements($consignors));
        $period = StatementPeriod::of(Fixtures::utc('2026-10-01'), Fixtures::utc('2026-11-01'));
        $author = Owner::consignor(Fixtures::AUTHOR);

        self::assertSame('ST-00000001', $numbering->next());
        self::assertSame('ST-00000001', $statements->issue($author, Currency::of('CAD'), $period, Fixtures::utc('2026-11-02'))->number);
        self::assertSame('ST-00000002', $numbering->next());
        self::assertSame('ST-00000002', $statements->issue($author, Currency::of('CAD'), $period, Fixtures::utc('2026-11-02'))->number);
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame('ST-00000003', new DoctrineStatementNumbering($entityManager)->next());
        self::assertSame('2026/C-00000001', new DoctrineStatementNumbering($entityManager, '2026/C-')->next());
        self::assertSame('00000001', new DoctrineStatementNumbering($entityManager, '')->next());
        self::assertSame('ST00000001', new DoctrineStatementNumbering($entityManager, 'ST')->next());
        self::assertSame('ST-00000002', $consignors->statement('ST-00000002')?->number);

        foreach (['ST ', 'ST_', 'ST%', str_repeat('S', 25), "ST\n"] as $prefix) {
            try {
                new DoctrineStatementNumbering($entityManager, $prefix);
                self::fail($prefix);
            } catch (InvalidValue $invalid) {
                self::assertStringStartsWith('A statement prefix has up to 24 letters', $invalid->getMessage());
            }
        }

        self::assertSame(str_repeat('S', 24).'00000001', new DoctrineStatementNumbering($entityManager, str_repeat('S', 24))->next());

        try {
            new DoctrineStatementNumbering($entityManager, 'X'.str_repeat('é', 40));
            self::fail('A prefix of other characters was taken.');
        } catch (InvalidValue $invalidValue) {
            self::assertStringEndsWith('; "X'.str_repeat('é', 39).'" does not.', $invalidValue->getMessage());
        }

        // Numbers of another form, persisted and not flushed yet, are not of the series.
        foreach (['ST-900000009', 'XX-00000050'] as $number) {
            $consignors->addStatement(ConsignorStatement::issue($number, $author, Currency::of('CAD'), $period, [], [], Fixtures::utc('2026-11-02')));
        }

        self::assertSame('ST-00000003', new DoctrineStatementNumbering($entityManager)->next());
    }

    #[Override]
    protected function flushAndClear(): void
    {
        $this->entityManager()->flush();
        $this->entityManager()->clear();
    }
}
