<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Infrastructure;

use Phil\CommerceConsignmentBundle\Application\ConsignorAgreements;
use Phil\CommerceConsignmentBundle\Application\ConsignorPayouts;
use Phil\CommerceConsignmentBundle\Application\ConsignorStatements;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\Contract\ConsignorRepository;
use Phil\CommerceConsignmentBundle\Domain\Contract\LedgerRepository;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignmentAgreement;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorPayout;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\OwnerQuantity;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryStatementNumbering;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * What every ledger repository does, written once: the in-memory one and Doctrine's on SQLite, MySQL and PostgreSQL.
 */
abstract class RepositoryTestCase extends TestCase
{
    abstract protected function repository(): LedgerRepository;

    abstract protected function consignors(): ConsignorRepository;

    /**
     * Writes what was stored and forgets every object, so that what follows reads the rows again.
     */
    abstract protected function flushAndClear(): void;

    public function testTheLedgerLivesThroughAWholeDay(): void
    {
        $ledger = new Ledger($this->repository());
        $ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $ledger->receive(Fixtures::consigned(2, 'artisan-bo', ConsignmentTerms::commission(300_000), 'CS-2', Fixtures::BACK), Fixtures::at(9, 30));
        $this->flushAndClear();

        $sale = new Ledger($this->repository())->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 7, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(10));
        self::assertSame(10000 + 4000 - 6000 - 3000, $sale->margin()?->margin->minorUnits);
        $this->flushAndClear();

        $ledger = new Ledger($this->repository());
        $ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::BACK, 3, Fixtures::at(11));
        $ledger->transfer(Fixtures::BOOK, Fixtures::BACK, Fixtures::SHELF, 1, IssueOrder::OldestFirst, Fixtures::at(12), 'TR-1');
        $this->flushAndClear();

        $ledger = new Ledger($this->repository());
        self::assertSame(-1, $ledger->count(Fixtures::BOOK, Fixtures::SHELF, 3, Fixtures::at(13)));
        $this->flushAndClear();

        $ledger = new Ledger($this->repository());
        self::assertSame([[Fixtures::AUTHOR, 3]], $this->split($ledger, Fixtures::SHELF));
        self::assertSame([['artisan-bo', 2], [Fixtures::AUTHOR, 2]], $this->split($ledger, Fixtures::BACK));
        self::assertSame(7, $ledger->sold('ORD-1', Fixtures::BOOK));
        self::assertSame(
            ['received', 'received', 'received', 'sold', 'sold', 'returned', 'returned', 'transferred_out', 'transferred_in', 'counted'],
            array_map(static fn (LedgerEntry $entry): string => $entry->kind->value, $this->repository()->journal(Fixtures::BOOK)),
        );
        self::assertCount(3, $this->repository()->journal(Fixtures::BOOK, 3));
        self::assertCount(1, $this->repository()->journal(Fixtures::BOOK, 0));

        $entries = $this->repository()->entriesOf('ORD-1', Fixtures::BOOK);
        self::assertSame([-5, -2, 2, 1], array_map(static fn (LedgerEntry $entry): int => $entry->quantity, $entries));
        self::assertSame('2026-11-20 15:00:00', $entries[0]->movedAt()->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $entries[0]->movedAt()->getTimezone()->getName());
        self::assertSame(2000, $entries[0]->unitPrice()?->minorUnits);
        self::assertSame(-1000, $entries[2]->margin()?->margin->minorUnits);
        self::assertSame([], $this->repository()->entriesOf('ORD-1', 'PEN'));
    }

    public function testLayersAreFoundByPlaceByOriginAndByOwner(): void
    {
        $repository = $this->repository();
        $ledger = new Ledger($repository);
        [$bought] = $ledger->receive(Fixtures::purchased(3, 1000, 'DN-1'), Fixtures::at(9));
        $ledger->receive(Fixtures::purchased(2, 1100, 'DN-2'), Fixtures::at(10));
        $ledger->receive(Receipt::of('DN-3', Fixtures::SHELF, [ReceiptLine::purchased('bOOK-1', 1, Fixtures::cad(1))]), Fixtures::at(11));
        $ledger->transfer(Fixtures::BOOK, Fixtures::SHELF, Fixtures::BACK, 3, IssueOrder::OldestFirst, Fixtures::at(12));
        $this->flushAndClear();

        $repository = $this->repository();
        self::assertSame(['DN-2'], $this->references($repository->layersAt(Fixtures::BOOK, Fixtures::SHELF)));
        self::assertSame(['DN-1'], $this->references($repository->layersAt(Fixtures::BOOK, Fixtures::BACK)));
        self::assertEqualsCanonicalizing(['DN-1', 'DN-2'], $this->references($repository->layersOf(Fixtures::BOOK)));
        self::assertSame(['DN-3'], $this->references($repository->layersOf('bOOK-1')));
        self::assertSame([Fixtures::SHELF, Fixtures::BACK], array_map(static fn (StockLayer $layer): string => $layer->location, $repository->layersFrom($bought->origin)));
        self::assertSame([0, 3], array_map(static fn (StockLayer $layer): int => $layer->remaining(), $repository->layersFrom($bought->origin)));
        self::assertSame('DN-3', $repository->latestLayer('bOOK-1', '')?->reference);
        self::assertSame(1100, $repository->latestLayer(Fixtures::BOOK, '')?->unitCost()?->minorUnits);
        self::assertNull($repository->latestLayer(Fixtures::BOOK, Fixtures::AUTHOR));
        self::assertNull($repository->layer('00000000-0000-0000-0000-000000000000'));
        self::assertSame(3, $repository->layer($bought->id)?->received);
        self::assertSame([], $repository->layersAt(Fixtures::BOOK, 'nowhere'));
    }

    public function testWhatWasNotFlushedYetIsFoundToo(): void
    {
        $repository = $this->repository();
        $ledger = new Ledger($repository);
        $ledger->receive(Fixtures::purchased(1, 1000, 'DN-0'), Fixtures::at(8));
        $this->flushAndClear();

        $repository = $this->repository();
        $ledger = new Ledger($repository);
        $ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(10));
        $ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 6, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(10, 5));

        self::assertSame(8, $ledger->sold('ORD-1', Fixtures::BOOK));
        self::assertSame([[Fixtures::AUTHOR, 3]], $this->split($ledger, Fixtures::SHELF));
        self::assertSame('DN-1', $repository->latestLayer(Fixtures::BOOK, '')?->reference);
        self::assertSame(2, $ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::at(11))->quantity());
    }

    public function testAConsignorsEntriesHeldUnitsAndBalanceAreRead(): void
    {
        $ledger = new Ledger($this->repository());
        $ledger->receive(Fixtures::mixedReceipt('DN-1'), Fixtures::utc('2026-10-05 10:00:00'));
        $ledger->receive(Fixtures::consigned(2, 'artisan-bo', ConsignmentTerms::commission(300_000), 'CS-2', Fixtures::BACK), Fixtures::utc('2026-10-04 10:00:00'));
        $ledger->receive(Receipt::of('CS-3', Fixtures::BACK, [ReceiptLine::consigned('PEN', 2, Fixtures::AUTHOR, ConsignmentTerms::share(Money::of(400, Currency::of('USD'))))]), Fixtures::utc('2026-10-06 10:00:00'));
        $ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 7, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::utc('2026-10-10 10:00:00'));
        $ledger->sell('ORD-2', 'PEN', Fixtures::BACK, 1, Money::of(1000, Currency::of('USD')), IssueOrder::OwnedFirst, Fixtures::utc('2026-10-11 10:00:00'));
        $ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::utc('2026-11-01 00:00:00'));
        $this->flushAndClear();

        $repository = $this->repository();
        $entries = $repository->consignorEntries(Fixtures::AUTHOR, Fixtures::utc('2026-10-05 10:00:00'), Fixtures::utc('2026-11-01 00:00:00'), 10);
        self::assertSame(['received', 'received', 'sold', 'sold'], array_map(static fn (LedgerEntry $entry): string => $entry->kind->value, $entries));
        self::assertSame(['DN-1', 'CS-3', 'ORD-1', 'ORD-2'], array_map(static fn (LedgerEntry $entry): ?string => $entry->reference, $entries));
        self::assertCount(2, $repository->consignorEntries(Fixtures::AUTHOR, Fixtures::utc('2026-10-05 10:00:00'), Fixtures::utc('2026-11-01 00:00:01'), 2));
        self::assertCount(1, $repository->consignorEntries(Fixtures::AUTHOR, Fixtures::utc('2026-10-05 10:00:00'), Fixtures::utc('2026-11-01 00:00:01'), 0));
        self::assertCount(5, $repository->consignorEntries(Fixtures::AUTHOR, Fixtures::utc('2026-10-05 10:00:00'), Fixtures::utc('2026-11-01 00:00:01'), 10));
        self::assertCount(4, $repository->consignorEntries(Fixtures::AUTHOR, Fixtures::utc('2026-10-05 10:00:01'), Fixtures::utc('2026-11-01 00:00:01'), 10));
        self::assertSame([], $repository->consignorEntries('', Fixtures::utc('2026-11-01'), Fixtures::utc('2026-12-01'), 10));

        $held = $repository->heldBy(Fixtures::AUTHOR, Fixtures::utc('2026-11-01 00:00:00'), 10);
        self::assertSame([3, 1], array_values($this->sortedByUnits($held)));
        self::assertSame([4, 1], array_values($this->sortedByUnits($repository->heldBy(Fixtures::AUTHOR, Fixtures::utc('2026-11-01 00:00:01'), 10))));
        self::assertCount(1, $repository->heldBy(Fixtures::AUTHOR, Fixtures::utc('2026-11-01 00:00:01'), 1));
        self::assertCount(1, $repository->heldBy(Fixtures::AUTHOR, Fixtures::utc('2026-11-01 00:00:01'), 0));
        self::assertSame([], $repository->heldBy(Fixtures::AUTHOR, Fixtures::utc('2026-10-05 10:00:00'), 10));
        $ids = array_keys($held);
        self::assertSame($ids, array_values(array_unique($ids)));
        $sorted = $ids;
        sort($sorted, \SORT_STRING);
        self::assertSame($sorted, $ids);

        self::assertSame(3000 - 1500, $repository->owedTo(Fixtures::AUTHOR, 'CAD'));
        self::assertSame(400, $repository->owedTo(Fixtures::AUTHOR, 'USD'));
        self::assertSame(0, $repository->owedTo('artisan-bo', 'CAD'));
        self::assertSame(0, $repository->owedTo(Fixtures::AUTHOR, 'EUR'));

        self::assertSame(['artisan-bo', Fixtures::AUTHOR, Fixtures::AUTHOR], array_map(static fn (StockLayer $layer): string => $layer->owner()->key(), $repository->consignedLayers(null, 10)));
        self::assertSame(['DN-1', 'CS-3'], array_map(static fn (StockLayer $layer): ?string => $layer->reference, $repository->consignedLayers(Fixtures::AUTHOR, 10)));
        self::assertCount(1, $repository->consignedLayers(null, 1));
        self::assertCount(1, $repository->consignedLayers(null, 0));
        self::assertSame([], $repository->consignedLayers('nobody', 10));

        // What this request wrote and did not flush yet counts too.
        new Ledger($repository)->sell('ORD-3', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(2000), IssueOrder::ConsignedFirst, Fixtures::utc('2026-11-02 00:00:00'));
        self::assertSame(1500 + 1500, $repository->owedTo(Fixtures::AUTHOR, 'CAD'));
        self::assertSame([3, 1], array_values($this->sortedByUnits($repository->heldBy(Fixtures::AUTHOR, Fixtures::utc('2026-11-03'), 10))));
        self::assertCount(2, $repository->consignorEntries(Fixtures::AUTHOR, Fixtures::utc('2026-11-01'), Fixtures::utc('2026-11-03'), 10));
        self::assertSame(['returned', 'sold'], array_map(static fn (LedgerEntry $entry): string => $entry->kind->value, $repository->consignorEntries(Fixtures::AUTHOR, Fixtures::utc('2026-11-01'), Fixtures::utc('2026-11-03'), 10)));
        self::assertCount(1, $repository->consignorEntries(Fixtures::AUTHOR, Fixtures::utc('2026-11-01'), Fixtures::utc('2026-11-03'), 1));
        self::assertSame(3, $repository->consignedLayers(Fixtures::AUTHOR, 10)[0]->remaining());
    }

    public function testAgreementsStatementsAndPayoutsAreStored(): void
    {
        $author = Owner::consignor(Fixtures::AUTHOR);
        $consignors = $this->consignors();
        $consignors->addAgreement(ConsignmentAgreement::version($author, Fixtures::utc('2026-11-10'), 90, UnsoldPolicy::Extend, StatementFrequency::Quarterly, Fixtures::utc('2026-11-10'), 30, 2));
        $consignors->addAgreement(ConsignmentAgreement::version($author, Fixtures::utc('2026-10-01'), null, UnsoldPolicy::BuyOut, StatementFrequency::Weekly, Fixtures::utc('2026-10-01')));
        $consignors->addAgreement(ConsignmentAgreement::version(Owner::consignor('artisan-bo'), Fixtures::utc('2026-10-01'), 30, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-10-01')));
        self::assertCount(2, $consignors->agreementsOf(Fixtures::AUTHOR));

        $ledger = new Ledger($this->repository());
        $ledger->receive(Fixtures::mixedReceipt('DN-1'), Fixtures::utc('2026-10-05 10:00:00'));
        $ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 7, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::utc('2026-10-10 10:00:00'));
        $this->flushAndClear();

        $consignors = $this->consignors();
        [$first, $second] = $consignors->agreementsOf(Fixtures::AUTHOR);
        self::assertSame([null, UnsoldPolicy::BuyOut, StatementFrequency::Weekly, null, 0], [$first->termDays, $first->unsoldPolicy, $first->frequency, $first->extensionDays, $first->extensions]);
        self::assertSame([90, UnsoldPolicy::Extend, StatementFrequency::Quarterly, 30, 2], [$second->termDays, $second->unsoldPolicy, $second->frequency, $second->extensionDays, $second->extensions]);
        self::assertSame('2026-11-10 00:00:00 UTC', $second->effectiveFrom()->format('Y-m-d H:i:s T'));
        self::assertSame('2026-11-10 00:00:00 UTC', $second->recordedAt()->format('Y-m-d H:i:s T'));
        self::assertSame([], $consignors->agreementsOf('nobody'));

        $statements = new ConsignorStatements($this->repository(), $consignors, new InMemoryStatementNumbering('T-'), new ConsignorAgreements($consignors));
        $october = StatementPeriod::of(Fixtures::utc('2026-10-01'), Fixtures::utc('2026-11-01'));
        $statement = $statements->issue($author, Currency::of('CAD'), $october, Fixtures::utc('2026-11-02 08:00:00'));
        self::assertSame($statement, $consignors->statement('T-00000001'));
        $statements->issue($author, Currency::of('CAD'), $october, Fixtures::utc('2026-11-02 09:00:00'));
        $this->flushAndClear();

        $consignors = $this->consignors();
        $read = $consignors->statement('T-00000001');
        self::assertInstanceOf(ConsignorStatement::class, $read);
        self::assertSame($statement->lines()[0]->toArray(), $read->lines()[0]->toArray());
        self::assertCount(1, $read->lines());
        self::assertSame($statement->held()[0]->toArray(), $read->held()[0]->toArray());
        self::assertSame([2, 4000, 3000, 1000], [$read->unitsSold, $read->revenue()->minorUnits, $read->share()->minorUnits, $read->commission()->minorUnits]);
        self::assertSame(['2026-10-01 00:00 UTC', '2026-11-01 00:00 UTC', '2026-11-02 08:00 UTC'], [$read->period()->start->format('Y-m-d H:i T'), $read->period()->end->format('Y-m-d H:i T'), $read->issuedAt()->format('Y-m-d H:i T')]);
        self::assertSame(['T-00000002', 'T-00000001'], array_map(static fn (ConsignorStatement $statement): string => $statement->number, $consignors->statementsOf(Fixtures::AUTHOR, 100)));
        self::assertCount(1, $consignors->statementsOf(Fixtures::AUTHOR, 0));
        self::assertNull($consignors->statement('T-00000009'));
        self::assertSame([], $consignors->statementsOf('nobody', 100));

        $payouts = new ConsignorPayouts($this->repository(), $consignors);
        $paid = $payouts->record('PAY-1', $author, Fixtures::cad(1000), Fixtures::utc('2026-11-03'), 'cheque', '000123', Fixtures::utc('2026-11-03 10:00:00'));
        $payouts->record('PAY-2', $author, Fixtures::cad(500), Fixtures::utc('2026-11-03'), 'cash', null, Fixtures::utc('2026-11-03 11:00:00'));
        self::assertSame($paid, $consignors->payoutByKey('PAY-1'));
        self::assertSame(1500, $consignors->paidTo(Fixtures::AUTHOR, 'CAD'));
        $this->flushAndClear();

        $consignors = $this->consignors();
        $payouts = new ConsignorPayouts($this->repository(), $consignors);
        $payouts->reverse($paid->id, 'PAY-1-R', Fixtures::utc('2026-11-04'));
        self::assertSame(500, $consignors->paidTo(Fixtures::AUTHOR, 'CAD'));
        self::assertSame($paid->id, $consignors->reversalOf($paid->id)?->reverses);
        $this->flushAndClear();

        $consignors = $this->consignors();
        $read = $consignors->payout($paid->id);
        self::assertInstanceOf(ConsignorPayout::class, $read);
        self::assertSame(['PAY-1', Fixtures::AUTHOR, 1000, 'cheque', '000123', null], [$read->idempotencyKey, $read->consignor, $read->amount()->minorUnits, $read->method, $read->reference, $read->reverses]);
        self::assertSame('2026-11-03 10:00 UTC', $read->recordedAt()->format('Y-m-d H:i T'));
        self::assertSame('2026-11-03 00:00 UTC', $read->paidAt()->format('Y-m-d H:i T'));
        self::assertSame('PAY-2', $consignors->payoutByKey('PAY-2')?->idempotencyKey);
        self::assertNull($consignors->payoutByKey('PAY-9'));
        self::assertSame('PAY-1-R', $consignors->reversalOf($paid->id)?->idempotencyKey);
        $two = $consignors->payoutByKey('PAY-2');
        self::assertInstanceOf(ConsignorPayout::class, $two);
        self::assertNull($consignors->reversalOf($two->id));
        self::assertNull($consignors->payout('00000000-0000-0000-0000-000000000000'));
        self::assertSame(['PAY-1', 'PAY-2', 'PAY-1-R'], array_map(static fn (ConsignorPayout $payout): string => $payout->idempotencyKey, $consignors->payoutsOf(Fixtures::AUTHOR, 'CAD', 500)));
        self::assertCount(1, $consignors->payoutsOf(Fixtures::AUTHOR, 'CAD', 0));
        self::assertSame([], $consignors->payoutsOf(Fixtures::AUTHOR, 'USD', 500));
        self::assertSame(500, $consignors->paidTo(Fixtures::AUTHOR, 'CAD'));
        self::assertSame(0, $consignors->paidTo(Fixtures::AUTHOR, 'USD'));
        self::assertSame(0, $consignors->paidTo('nobody', 'CAD'));
        self::assertSame(2500, $payouts->balance($author, Currency::of('CAD'))->balance->minorUnits);
    }

    public function testTheJournalKeepsToItsSkuAndToFiveHundredEntriesByDefault(): void
    {
        $ledger = new Ledger($this->repository());
        $ledger->receive(Receipt::of('DN-0', Fixtures::SHELF, [ReceiptLine::purchased('PEN', 1, Fixtures::cad(1))]), Fixtures::at(8));
        $ledger->receive(Receipt::of('DN-1', Fixtures::SHELF, array_fill(0, 500, ReceiptLine::purchased(Fixtures::BOOK, 1, Fixtures::cad(1)))), Fixtures::at(9));
        $ledger->receive(Fixtures::purchased(1, 1, 'DN-2'), Fixtures::at(10));
        $this->flushAndClear();

        self::assertCount(500, $this->repository()->journal(Fixtures::BOOK));
        self::assertSame(['DN-0'], array_map(static fn (LedgerEntry $entry): ?string => $entry->reference, $this->repository()->journal('PEN')));
    }

    public function testEmptiedLayersAndEntriesWrittenOutOfOrderAreReadRight(): void
    {
        $ledger = new Ledger($this->repository());
        $ledger->receive(Fixtures::consigned(2, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-1'), Fixtures::utc('2026-10-05 10:00:00'));
        $ledger->receive(Fixtures::consigned(1, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-2', Fixtures::BACK), Fixtures::utc('2026-10-04 10:00:00'));
        // Written after the receipt, dated before the receipt of CS-1.
        $ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::BACK, 1, Fixtures::cad(1000), IssueOrder::OldestFirst, Fixtures::utc('2026-10-04 12:00:00'));
        $this->flushAndClear();

        $repository = $this->repository();
        self::assertSame(['CS-2', 'ORD-1', 'CS-1'], array_map(static fn (LedgerEntry $entry): ?string => $entry->reference, $repository->consignorEntries(Fixtures::AUTHOR, Fixtures::utc('2026-10-01'), Fixtures::utc('2026-11-01'), 10)));
        self::assertSame([2], array_values($repository->heldBy(Fixtures::AUTHOR, Fixtures::utc('2026-11-01'), 10)));
        self::assertSame(['CS-1'], array_map(static fn (StockLayer $layer): ?string => $layer->reference, $repository->consignedLayers(Fixtures::AUTHOR, 10)));
        self::assertSame(['CS-1'], array_map(static fn (StockLayer $layer): ?string => $layer->reference, $repository->consignedLayers(null, 10)));
    }

    public function testPayoutsAreReadPerConsignorInTheOrderTheyWereWritten(): void
    {
        $consignors = $this->consignors();
        $consignors->addPayout(ConsignorPayout::record('P-2', Owner::consignor(Fixtures::AUTHOR), Fixtures::cad(100), Fixtures::at(9), 'cash', null, Fixtures::at(11)));
        $consignors->addPayout(ConsignorPayout::record('P-1', Owner::consignor(Fixtures::AUTHOR), Fixtures::cad(200), Fixtures::at(9), 'cash', null, Fixtures::at(10)));
        $consignors->addPayout(ConsignorPayout::record('P-X', Owner::consignor('artisan-bo'), Fixtures::cad(300), Fixtures::at(9), 'cash', null, Fixtures::at(10, 30)));
        self::assertSame(300, $consignors->paidTo(Fixtures::AUTHOR, 'CAD'));
        $consignors->addAgreement(ConsignmentAgreement::version(Owner::consignor(Fixtures::AUTHOR), Fixtures::utc('2026-11-10'), 1, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-11-10')));
        $consignors->addAgreement(ConsignmentAgreement::version(Owner::consignor(Fixtures::AUTHOR), Fixtures::utc('2026-10-01'), 2, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-10-01')));
        self::assertSame([2, 1], array_map(static fn (ConsignmentAgreement $agreement): ?int => $agreement->termDays, $consignors->agreementsOf(Fixtures::AUTHOR)));
        $this->flushAndClear();

        self::assertSame(['P-1', 'P-2'], array_map(static fn (ConsignorPayout $payout): string => $payout->idempotencyKey, $this->consignors()->payoutsOf(Fixtures::AUTHOR, 'CAD', 10)));
        self::assertSame([2, 1], array_map(static fn (ConsignmentAgreement $agreement): ?int => $agreement->termDays, $this->consignors()->agreementsOf(Fixtures::AUTHOR)));
    }

    public function testStatementsAreListedTheLastIssuedFirst(): void
    {
        $period = StatementPeriod::of(Fixtures::utc('2026-10-01'), Fixtures::utc('2026-11-01'));
        $consignors = $this->consignors();

        foreach (['B' => '2026-11-03', 'C' => '2026-11-05', 'A' => '2026-11-02', 'D' => '2026-11-04'] as $number => $issuedAt) {
            $consignors->addStatement(ConsignorStatement::issue($number, Owner::consignor(Fixtures::AUTHOR), Currency::of('CAD'), $period, [], [], Fixtures::utc($issuedAt)));
        }

        $this->flushAndClear();

        self::assertSame(['C', 'D', 'B', 'A'], array_map(static fn (ConsignorStatement $statement): string => $statement->number, $this->consignors()->statementsOf(Fixtures::AUTHOR, 10)));
        self::assertSame(['C', 'D'], array_map(static fn (ConsignorStatement $statement): string => $statement->number, $this->consignors()->statementsOf(Fixtures::AUTHOR, 2)));
    }

    /**
     * @param array<string, int> $held
     *
     * @return array<string, int>
     */
    private function sortedByUnits(array $held): array
    {
        arsort($held);

        return $held;
    }

    /**
     * @return list<array{string, int}>
     */
    private function split(Ledger $ledger, string $location): array
    {
        return array_map(static fn (OwnerQuantity $split): array => [$split->owner->key(), $split->quantity], $ledger->split(Fixtures::BOOK, $location));
    }

    /**
     * @param list<StockLayer> $layers
     *
     * @return list<string|null>
     */
    private function references(array $layers): array
    {
        return array_map(static fn (StockLayer $layer): ?string => $layer->reference, $layers);
    }
}
