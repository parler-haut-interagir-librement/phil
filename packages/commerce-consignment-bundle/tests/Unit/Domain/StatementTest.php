<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Domain;

use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\HeldUnits;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Margin;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsignorStatement::class)]
#[CoversClass(StatementLine::class)]
#[CoversClass(HeldUnits::class)]
final class StatementTest extends TestCase
{
    public function testAStatementSumsItsLinesAndFreezesThem(): void
    {
        $layer = $this->layer();
        $lines = [
            $this->line($layer, EntryKind::Sold, -3, 2000, 1400, 9),
            $this->line($layer, EntryKind::Returned, 1, 2000, 1400, 10),
            $this->line($layer, EntryKind::BoughtOut, -2, 900, 900, 11),
            StatementLine::fromEntry(LedgerEntry::movement($layer, EntryKind::GivenBack, -4, 'RS-1', Fixtures::at(12)), $this->cad()),
        ];
        $held = [HeldUnits::at($layer->id, Fixtures::BOOK, Fixtures::SHELF, 6, Fixtures::utc('2026-11-01 12:00:00'), Fixtures::utc('2026-11-21 11:59:59'))];

        $statement = ConsignorStatement::issue('ST-00000001', Owner::consignor(Fixtures::AUTHOR), $this->cad(), $this->period(), $lines, $held, Fixtures::at(23));

        self::assertSame('ST-00000001', $statement->number);
        self::assertSame(Fixtures::AUTHOR, $statement->consignor);
        self::assertSame('CAD', $statement->currency()->code);
        self::assertSame(['2026-11-20 00:00', '2026-11-21 00:00'], [$statement->period()->start->format('Y-m-d H:i'), $statement->period()->end->format('Y-m-d H:i')]);
        self::assertSame('2026-11-21 04:00:00 UTC', $statement->issuedAt()->format('Y-m-d H:i:s T'));
        self::assertSame([3, 1, 2, 4], [$statement->unitsSold, $statement->unitsReturned, $statement->unitsBoughtOut, $statement->unitsGivenBack]);
        self::assertSame(6000 - 2000 + 1800, $statement->revenue()->minorUnits);
        self::assertSame(4200 - 1400 + 1800, $statement->share()->minorUnits);
        self::assertSame(1800 - 600, $statement->commission()->minorUnits);
        self::assertSame('CAD', $statement->share()->currency->code);
        self::assertSame(6, $statement->unitsHeld());
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $statement->id);

        $read = $statement->lines();
        self::assertCount(4, $read);
        self::assertSame([3, -1, 2, 4], array_map(static fn (StatementLine $line): int => $line->units, $read));
        self::assertSame(['sold', 'returned', 'bought_out', 'given_back'], array_map(static fn (StatementLine $line): string => $line->kind->value, $read));
        self::assertSame([6000, -2000, 1800, 0], array_map(static fn (StatementLine $line): int => $line->revenue->minorUnits, $read));
        self::assertSame([1800, -600, 0, 0], array_map(static fn (StatementLine $line): int => $line->commission->minorUnits, $read));
        self::assertSame($lines[0]->toArray(), $read[0]->toArray());
        self::assertSame('2026-11-20T14:00:00.000000Z', $read[0]->toArray()['moved_at']);
        self::assertSame('UTC', $read[0]->movedAt->getTimezone()->getName());
        self::assertSame('ORD-1', $read[0]->reference);
        self::assertSame(Fixtures::SHELF, $read[0]->location);
        self::assertSame(Fixtures::BOOK, $read[0]->sku);

        [$units] = $statement->held();
        self::assertSame([$layer->id, Fixtures::BOOK, Fixtures::SHELF, 6, 19], [$units->layer, $units->sku, $units->location, $units->units, $units->ageInDays]);
        self::assertSame('2026-11-01T12:00:00.000000Z', $units->toArray()['received_at']);
        self::assertSame('UTC', $units->receivedAt->getTimezone()->getName());
    }

    public function testUnitsOfEachKindAddUp(): void
    {
        $layer = $this->layer();
        $lines = [
            $this->line($layer, EntryKind::Sold, -3, 2000, 1400, 9),
            $this->line($layer, EntryKind::Sold, -2, 2000, 1400, 10),
            $this->line($layer, EntryKind::BoughtOut, -2, 900, 900, 11),
            $this->line($layer, EntryKind::BoughtOut, -1, 900, 900, 12),
            $this->line($layer, EntryKind::Returned, 1, 2000, 1400, 13),
            $this->line($layer, EntryKind::Returned, 2, 2000, 1400, 14),
            StatementLine::fromEntry(LedgerEntry::movement($layer, EntryKind::GivenBack, -4, 'RS-1', Fixtures::at(15)), $this->cad()),
            StatementLine::fromEntry(LedgerEntry::movement($layer, EntryKind::GivenBack, -1, 'RS-2', Fixtures::at(16)), $this->cad()),
        ];

        $statement = ConsignorStatement::issue('ST-1', Owner::consignor(Fixtures::AUTHOR), $this->cad(), $this->period(), $lines, [], Fixtures::at(23));

        self::assertSame([5, 3, 3, 5], [$statement->unitsSold, $statement->unitsReturned, $statement->unitsBoughtOut, $statement->unitsGivenBack]);
    }

    public function testAnEmptyStatementOwesNothing(): void
    {
        $statement = ConsignorStatement::issue('A', Owner::consignor(Fixtures::AUTHOR), $this->cad(), $this->period(), [], [], Fixtures::at(23));

        self::assertSame([0, 0, 0, 0, 0], [$statement->unitsSold, $statement->unitsReturned, $statement->unitsBoughtOut, $statement->unitsGivenBack, $statement->unitsHeld()]);
        self::assertTrue($statement->share()->isZero());
        self::assertSame([], $statement->lines());
        self::assertSame([], $statement->held());
    }

    public function testTheAgeOfUnitsHeldIsInWholeDaysAndNeverNegative(): void
    {
        self::assertSame(0, HeldUnits::at('l', 's', 'p', 1, Fixtures::utc('2026-11-20 12:00:00'), Fixtures::utc('2026-11-21 11:59:59'))->ageInDays);
        self::assertSame(1, HeldUnits::at('l', 's', 'p', 1, Fixtures::utc('2026-11-20 12:00:00'), Fixtures::utc('2026-11-21 12:00:00'))->ageInDays);
        self::assertSame(0, HeldUnits::at('l', 's', 'p', 1, Fixtures::utc('2026-11-22 12:00:00'), Fixtures::utc('2026-11-21 12:00:00'))->ageInDays);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badNumbers(): iterable
    {
        yield 'empty' => ['', 'A statement number has 1 to 32 visible ASCII characters; "" does not.'];
        yield 'a space' => ['ST 1', 'A statement number has 1 to 32 visible ASCII characters; "ST 1" does not.'];
        yield 'too long' => [str_repeat('9', 33), 'A statement number has 1 to 32 visible ASCII characters;'];
        yield 'a new line' => ["ST-1\n", 'A statement number has 1 to 32 visible ASCII characters;'];
    }

    #[DataProvider('badNumbers')]
    public function testAStatementNumberIsChecked(string $number, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);
        ConsignorStatement::issue($number, Owner::consignor(Fixtures::AUTHOR), $this->cad(), $this->period(), [], [], Fixtures::at(23));
    }

    public function testThirtyTwoCharactersFitANumber(): void
    {
        $number = '!'.str_repeat('~', 31);

        self::assertSame($number, ConsignorStatement::issue($number, Owner::consignor(Fixtures::AUTHOR), $this->cad(), $this->period(), [], [], Fixtures::at(23))->number);
    }

    public function testAStatementGoesToAConsignor(): void
    {
        $this->expectExceptionMessageIsOrContains('A statement is issued to a consignor, not to the shop.');
        ConsignorStatement::issue('ST-1', Owner::shop(), $this->cad(), $this->period(), [], [], Fixtures::at(23));
    }

    public function testAStatementOnlyListsWhatConcernsTheConsignor(): void
    {
        $layer = $this->layer();

        $this->expectExceptionMessageIsOrContains('A statement lists units sold, returned, bought out or given back; not counted.');
        ConsignorStatement::issue('ST-1', Owner::consignor(Fixtures::AUTHOR), $this->cad(), $this->period(), [StatementLine::fromEntry(LedgerEntry::movement($layer, EntryKind::Counted, -1, null, Fixtures::at(9)), $this->cad())], [], Fixtures::at(23));
    }

    public function testAStatementIsBounded(): void
    {
        $line = StatementLine::fromEntry(LedgerEntry::movement($this->layer(), EntryKind::GivenBack, -1, null, Fixtures::at(9)), $this->cad());
        $held = HeldUnits::at('l', 's', 'p', 1, Fixtures::at(9), Fixtures::at(10));
        $statement = ConsignorStatement::issue('ST-1', Owner::consignor(Fixtures::AUTHOR), $this->cad(), $this->period(), array_fill(0, 5000, $line), array_fill(0, 1000, $held), Fixtures::at(23));
        self::assertSame(5000, $statement->unitsGivenBack);
        self::assertSame(1000, $statement->unitsHeld());

        foreach ([[5001, 0], [0, 1001]] as [$lines, $layers]) {
            try {
                ConsignorStatement::issue('ST-1', Owner::consignor(Fixtures::AUTHOR), $this->cad(), $this->period(), array_fill(0, $lines, $line), array_fill(0, $layers, $held), Fixtures::at(23));
                self::fail('An unbounded statement was issued.');
            } catch (InvalidValue $invalid) {
                self::assertSame('A statement holds 5000 lines and 1000 layers held at most: split the period.', $invalid->getMessage());
            }
        }
    }

    public function testALineReadBackIsChecked(): void
    {
        $row = $this->line($this->layer(), EntryKind::Sold, -1, 2000, 1500, 9)->toArray();

        foreach ([
            'A statement line whose share and commission do not add up to its revenue was not frozen by the ledger.' => ['commission' => 501] + $row,
            'A statement line holds an integer "units".' => ['units' => '1'] + $row,
            'A statement line holds a string "sku".' => ['sku' => 1] + $row,
            'A statement line holds an integer "revenue".' => array_diff_key($row, ['revenue' => true]),
        ] as $message => $bad) {
            try {
                StatementLine::fromArray($bad, $this->cad());
                self::fail($message);
            } catch (InvalidValue $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        self::assertNull(StatementLine::fromArray(['reference' => null] + $row, $this->cad())->reference);
    }

    public function testUnitsHeldReadBackAreChecked(): void
    {
        $row = HeldUnits::at('l', 's', 'p', 1, Fixtures::at(9), Fixtures::at(10))->toArray();

        foreach ([
            'Units held hold a string "sku".' => ['sku' => 2] + $row,
            'Units held hold an integer "units".' => ['units' => '1'] + $row,
            'Units held hold an integer "age_in_days".' => array_diff_key($row, ['age_in_days' => true]),
        ] as $message => $bad) {
            try {
                HeldUnits::fromArray($bad);
                self::fail($message);
            } catch (InvalidValue $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        self::assertSame($row, HeldUnits::fromArray($row)->toArray());
    }

    private function cad(): Currency
    {
        return Currency::of('CAD');
    }

    private function period(): StatementPeriod
    {
        return StatementPeriod::of(Fixtures::utc('2026-11-20 00:00:00'), Fixtures::utc('2026-11-21 00:00:00'));
    }

    private function layer(): StockLayer
    {
        return StockLayer::receive(ReceiptLine::consigned(Fixtures::BOOK, 10, Fixtures::AUTHOR, ConsignmentTerms::commission(1)), Fixtures::SHELF, 'CS-1', Fixtures::at(8));
    }

    private function line(StockLayer $layer, EntryKind $kind, int $quantity, int $unitPrice, int $unitShare, int $hour): StatementLine
    {
        $units = abs($quantity);
        $sign = $quantity < 0 ? 1 : -1;
        $margin = Margin::of(Fixtures::cad($sign * $units * $unitPrice), Fixtures::cad($sign * $units * $unitShare));

        return StatementLine::fromEntry(LedgerEntry::sale($layer, $kind, $quantity, 'ORD-1', Fixtures::cad($unitPrice), $margin, Fixtures::at($hour)), $this->cad());
    }
}
