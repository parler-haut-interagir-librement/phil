<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Application;

use Phil\CommerceConsignmentBundle\Application\ConsignorAgreements;
use Phil\CommerceConsignmentBundle\Application\ConsignorStatements;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\HeldUnits;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryConsignorRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryLedgerRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryStatementNumbering;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsignorStatements::class)]
#[CoversClass(InMemoryStatementNumbering::class)]
#[CoversClass(ConsignmentRefused::class)]
final class ConsignorStatementsTest extends TestCase
{
    private Ledger $ledger;

    private InMemoryConsignorRepository $consignors;

    private ConsignorAgreements $agreements;

    private ConsignorStatements $statements;

    private Owner $author;

    private Currency $cad;

    protected function setUp(): void
    {
        $repository = new InMemoryLedgerRepository();
        $this->ledger = new Ledger($repository);
        $this->consignors = new InMemoryConsignorRepository();
        $this->agreements = new ConsignorAgreements($this->consignors);
        $this->statements = new ConsignorStatements($repository, $this->consignors, new InMemoryStatementNumbering(), $this->agreements);
        $this->author = Owner::consignor(Fixtures::AUTHOR);
        $this->cad = Currency::of('CAD');
    }

    public function testStatementsAreReadFromTheEntriesAndNeverChangeOnceIssued(): void
    {
        // October: five copies bought, five consigned on a share of 15.00; seven sold at 20.00, the shop's first, and one
        // more in the last second of the month.
        $this->ledger->receive(Fixtures::mixedReceipt('DN-1'), Fixtures::utc('2026-10-05 10:00:00'));
        $this->ledger->receive(Fixtures::consigned(2, 'artisan-bo', ConsignmentTerms::commission(300_000), 'CS-2'), Fixtures::utc('2026-10-06 10:00:00'));
        $this->ledger->receive(Receipt::of('CS-3', Fixtures::SHELF, [ReceiptLine::consigned('PEN', 3, Fixtures::AUTHOR, ConsignmentTerms::share(Money::of(500, Currency::of('USD'))))]), Fixtures::utc('2026-10-07 10:00:00'));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 7, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::utc('2026-10-10 10:00:00'));
        $this->ledger->sell('ORD-2', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(2000), IssueOrder::ConsignedFirst, Fixtures::utc('2026-10-31 23:59:59'));
        $this->ledger->sell('ORD-3', 'PEN', Fixtures::SHELF, 1, Money::of(900, Currency::of('USD')), IssueOrder::OldestFirst, Fixtures::utc('2026-10-12 10:00:00'));
        // November, before the end of the period: a copy of ORD-1 brought back, one bought by the shop, one given back.
        $this->ledger->sell('ORD-4', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(2000), IssueOrder::ConsignedFirst, Fixtures::utc('2026-11-01 00:00:00'));

        $october = $this->statements->issue($this->author, $this->cad, StatementPeriod::of(Fixtures::utc('2026-10-01'), Fixtures::utc('2026-11-01')), Fixtures::utc('2026-11-02 09:00:00'));

        self::assertSame('ST-00000001', $october->number);
        self::assertSame($october, $this->consignors->statement('ST-00000001'));
        self::assertSame([3, 0, 0, 0], [$october->unitsSold, $october->unitsReturned, $october->unitsBoughtOut, $october->unitsGivenBack]);
        self::assertSame([6000, 4500, 1500], [$october->revenue()->minorUnits, $october->share()->minorUnits, $october->commission()->minorUnits]);
        self::assertSame(['ORD-1', 'ORD-2'], array_map(static fn (StatementLine $line): ?string => $line->reference, $october->lines()));
        self::assertSame([[Fixtures::BOOK, 2, 26], ['PEN', 2, 24]], array_map(static fn (HeldUnits $units): array => [$units->sku, $units->units, $units->ageInDays], $october->held()));

        $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::utc('2026-11-03 10:00:00'));
        $this->ledger->buyOut('BO-1', $this->author, Fixtures::BOOK, Fixtures::SHELF, 1, null, Fixtures::utc('2026-11-04 10:00:00'));
        $this->ledger->giveBack('RS-1', $this->author, Fixtures::SHELF, [Fixtures::BOOK => 1, 'PEN' => 2], Fixtures::utc('2026-11-05 10:00:00'));

        $november = $this->statements->issue($this->author, $this->cad, StatementPeriod::of(Fixtures::utc('2026-11-01'), Fixtures::utc('2026-12-01')), Fixtures::utc('2026-12-01'));

        self::assertSame('ST-00000002', $november->number);
        self::assertSame([1, 1, 1, 3], [$november->unitsSold, $november->unitsReturned, $november->unitsBoughtOut, $november->unitsGivenBack]);
        self::assertSame(2000 - 2000 + 1500, $november->revenue()->minorUnits);
        self::assertSame(1500 - 1500 + 1500, $november->share()->minorUnits);
        self::assertSame(500 - 500, $november->commission()->minorUnits);
        self::assertSame(['ORD-4', 'ORD-1', 'BO-1', 'RS-1', 'RS-1'], array_map(static fn (StatementLine $line): ?string => $line->reference, $november->lines()));
        self::assertSame([], $november->held());

        // Issued again, October is a new statement; the first stays as it was.
        $again = $this->statements->issue($this->author, $this->cad, $october->period(), Fixtures::utc('2026-12-02'));
        self::assertSame('ST-00000003', $again->number);
        self::assertSame($october->lines()[1]->toArray(), $again->lines()[1]->toArray());
        self::assertSame(4500, $this->consignors->statement('ST-00000001')?->share()->minorUnits);
        self::assertSame(['ST-00000003', 'ST-00000002', 'ST-00000001'], array_map(static fn (ConsignorStatement $statement): string => $statement->number, $this->consignors->statementsOf(Fixtures::AUTHOR, 100)));
        self::assertSame(['ST-00000003'], array_map(static fn (ConsignorStatement $statement): string => $statement->number, $this->consignors->statementsOf(Fixtures::AUTHOR, 1)));

        // The pens sold in dollars are on the statement in dollars.
        $dollars = $this->statements->issue($this->author, Currency::of('USD'), $october->period(), Fixtures::utc('2026-12-02'));
        self::assertSame([1, 900, 500, 400], [$dollars->unitsSold, $dollars->revenue()->minorUnits, $dollars->share()->minorUnits, $dollars->commission()->minorUnits]);

        $artisan = $this->statements->issue(Owner::consignor('artisan-bo'), $this->cad, $october->period(), Fixtures::utc('2026-12-02'));
        self::assertSame([0, 2], [$artisan->unitsSold, $artisan->unitsHeld()]);
    }

    public function testAStatementCoversAPeriodThatIsOverForAConsignor(): void
    {
        $period = StatementPeriod::of(Fixtures::utc('2026-11-01'), Fixtures::utc('2026-12-01'));
        self::assertSame('ST-00000001', $this->statements->issue($this->author, $this->cad, $period, Fixtures::utc('2026-12-01'))->number);

        try {
            $this->statements->issue($this->author, $this->cad, $period, Fixtures::utc('2026-11-30 23:59:59'));
            self::fail('A statement of a period not over was issued.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('A statement covers a period that is over; the period ends at 2026-12-01T00:00:00+00:00, after 2026-11-30T23:59:59+00:00.', $consignmentRefused->getMessage());
        }

        $this->expectExceptionMessageIsOrContains("A statement concerns a consignor's units, not the shop's.");
        $this->statements->issue(Owner::shop(), $this->cad, $period, Fixtures::utc('2026-12-01'));
    }

    public function testAStatementHoldsAtMostFiveThousandLines(): void
    {
        $this->ledger->receive(Fixtures::consigned(5001, Fixtures::AUTHOR, ConsignmentTerms::commission(1)), Fixtures::utc('2026-10-31 23:59:59'));

        foreach (range(1, 5000) as $i) {
            $this->ledger->giveBack('RS-'.$i, $this->author, Fixtures::SHELF, [Fixtures::BOOK => 1], Fixtures::utc('2026-11-02'));
        }

        $period = StatementPeriod::of(Fixtures::utc('2026-11-01'), Fixtures::utc('2026-12-01'));
        $statement = $this->statements->issue($this->author, $this->cad, $period, Fixtures::utc('2026-12-01'));
        self::assertSame(5000, $statement->unitsGivenBack);
        self::assertSame(1, $statement->unitsHeld());

        $this->ledger->giveBack('RS-LAST', $this->author, Fixtures::SHELF, [Fixtures::BOOK => 1], Fixtures::utc('2026-11-02'));

        $this->expectExceptionMessageIsOrContains('The ledger holds more than 5000 entries for author-ann over the period: issue statements of shorter periods.');
        $this->statements->issue($this->author, $this->cad, $period, Fixtures::utc('2026-12-01'));
    }

    public function testAStatementListsTheUnitsOfAtMostAThousandLayers(): void
    {
        foreach ([0, 500] as $offset) {
            $this->ledger->receive(Receipt::of('CS-'.$offset, Fixtures::SHELF, array_map(static fn (int $i): ReceiptLine => ReceiptLine::consigned('S'.($offset + $i), 1, Fixtures::AUTHOR, ConsignmentTerms::commission(1)), range(1, 500))), Fixtures::utc('2026-10-01'));
        }

        $period = StatementPeriod::of(Fixtures::utc('2026-11-01'), Fixtures::utc('2026-12-01'));
        self::assertSame(1000, $this->statements->issue($this->author, $this->cad, $period, Fixtures::utc('2026-12-01'))->unitsHeld());

        $this->ledger->receive(Fixtures::consigned(1, Fixtures::AUTHOR, ConsignmentTerms::commission(1)), Fixtures::utc('2026-10-01'));

        $this->expectExceptionMessageIsOrContains('The ledger holds more than 1000 entries for author-ann');
        $this->statements->issue($this->author, $this->cad, $period, Fixtures::utc('2026-12-01'));
    }

    public function testTheUnitsHeldAreListedOldestReceiptFirst(): void
    {
        // Written first, received last: the layers' identifiers are in the other order.
        $this->ledger->receive(Fixtures::consigned(1, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-LATE'), Fixtures::utc('2026-10-20'));
        $this->ledger->receive(Fixtures::consigned(2, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-EARLY'), Fixtures::utc('2026-10-02'));
        $this->ledger->receive(Fixtures::consigned(3, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-MID'), Fixtures::utc('2026-10-10'));

        $held = $this->statements->issue($this->author, $this->cad, StatementPeriod::of(Fixtures::utc('2026-10-01'), Fixtures::utc('2026-11-01')), Fixtures::utc('2026-11-01'))->held();

        self::assertSame([2, 3, 1], array_map(static fn (HeldUnits $units): int => $units->units, $held));
    }

    public function testTheLastPeriodFollowsTheAgreementOfTheDay(): void
    {
        self::assertSame('2026-10-01', $this->statements->lastPeriod($this->author, Fixtures::utc('2026-11-20'))->start->format('Y-m-d'));

        $this->agreements->agree($this->author, Fixtures::utc('2026-11-01'), null, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Weekly, Fixtures::utc('2026-11-01'));
        self::assertSame('2026-11-09', $this->statements->lastPeriod($this->author, Fixtures::utc('2026-11-20'))->start->format('Y-m-d'));
        self::assertSame('2026-09-01', $this->statements->lastPeriod($this->author, Fixtures::utc('2026-10-20'))->start->format('Y-m-d'));
    }
}
