<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Domain;

use Closure;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\OwnerQuantity;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryLedgerRepository;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the ledger checks before it looks for a layer, and the returns and counts that read several layers.
 */
#[CoversClass(Ledger::class)]
#[CoversClass(StockLayer::class)]
final class LedgerGuardsTest extends TestCase
{
    private InMemoryLedgerRepository $repository;

    private Ledger $ledger;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLedgerRepository();
        $this->ledger = new Ledger($this->repository);
    }

    /**
     * @return iterable<string, array{string, Closure(Ledger): mixed}>
     */
    public static function refusedWithoutStock(): iterable
    {
        $quantity = 'A quantity is one unit or more; 0 is not.';

        yield 'a sale of nothing' => [$quantity, static fn (Ledger $ledger): mixed => $ledger->sell('ORD-1', 'NONE', Fixtures::SHELF, 0, Fixtures::cad(1), IssueOrder::OldestFirst, Fixtures::at(9))];
        yield 'a return of nothing' => [$quantity, static fn (Ledger $ledger): mixed => $ledger->returnSold('ORD-1', 'NONE', Fixtures::SHELF, 0, Fixtures::at(9))];
        yield 'a return to no location' => ['A location has 1 to 64 characters, none of them a control character; "" does not.', static fn (Ledger $ledger): mixed => $ledger->returnSold('ORD-1', 'NONE', '', 1, Fixtures::at(9))];
        yield 'a transfer of nothing' => [$quantity, static fn (Ledger $ledger): mixed => $ledger->transfer('NONE', Fixtures::SHELF, Fixtures::BACK, 0, IssueOrder::OldestFirst, Fixtures::at(9))];
        yield 'a slip of nothing' => [$quantity, static fn (Ledger $ledger): mixed => $ledger->giveBack('RS-1', Owner::consignor(Fixtures::AUTHOR), Fixtures::SHELF, ['NONE' => 0], Fixtures::at(9))];
        yield 'a buy-out of nothing' => [$quantity, static fn (Ledger $ledger): mixed => $ledger->buyOut('BO-1', Owner::consignor(Fixtures::AUTHOR), 'NONE', Fixtures::SHELF, 0, Fixtures::cad(1), Fixtures::at(9))];
    }

    /**
     * @param Closure(Ledger): mixed $operation
     */
    #[DataProvider('refusedWithoutStock')]
    public function testAValueIsCheckedEvenWhereNothingIsHeld(string $message, Closure $operation): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);
        $operation($this->ledger);
    }

    public function testACountMayFindNothing(): void
    {
        $this->ledger->receive(Fixtures::purchased(2, 1000), Fixtures::at(9));

        self::assertSame(-2, $this->ledger->count(Fixtures::BOOK, Fixtures::SHELF, 0, Fixtures::at(10)));
        self::assertSame(0, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF));

        try {
            $this->ledger->count(Fixtures::BOOK, Fixtures::SHELF, -1, Fixtures::at(10));
            self::fail('A count found fewer than no unit.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('A count finds zero units or more; -1 is not.', $invalidValue->getMessage());
        }
    }

    public function testUnitsFoundGoToTheNewestReceiptWhicheverWasWrittenFirst(): void
    {
        $this->ledger->receive(Fixtures::purchased(2, 1100, 'DN-NEW'), Fixtures::at(10));
        $this->ledger->receive(Fixtures::purchased(2, 1000, 'DN-OLD'), Fixtures::at(9));

        self::assertSame(3, $this->ledger->count(Fixtures::BOOK, Fixtures::SHELF, 7, Fixtures::at(12), 'COUNT-1'));

        $remaining = [];

        foreach ($this->repository->layersAt(Fixtures::BOOK, Fixtures::SHELF) as $layer) {
            $remaining[(string) $layer->reference] = $layer->remaining();
        }

        self::assertSame(['DN-NEW' => 5, 'DN-OLD' => 2], $remaining);
        $last = $this->repository->journal(Fixtures::BOOK)[2];
        self::assertSame([EntryKind::Counted, 3, 'COUNT-1'], [$last->kind, $last->quantity, $last->reference]);
    }

    public function testTwoSalesOfOneLayerUnderOneReferenceAreBothReturnable(): void
    {
        $this->ledger->receive(Fixtures::purchased(10, 1000), Fixtures::at(9));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(2000), IssueOrder::OldestFirst, Fixtures::at(10));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 3, Fixtures::cad(2000), IssueOrder::OldestFirst, Fixtures::at(11));

        self::assertSame(5, $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 5, Fixtures::at(12))->quantity());
        self::assertSame(10, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF));
    }

    public function testALayerReturnedWhollyIsSkippedByTheNextReturn(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 7, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(10));
        $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::at(11));

        $sale = $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::at(12));

        self::assertTrue($sale->lines[0]->owner->equals(Owner::shop()));
        self::assertSame([2, 1], array_map(static fn (LedgerEntry $entry): int => $entry->quantity, \array_slice($this->repository->entriesOf('ORD-1', Fixtures::BOOK), 2)));
    }

    public function testTheSplitKeepsANumericConsignorApart(): void
    {
        $this->ledger->receive(Fixtures::consigned(1, '17', ConsignmentTerms::commission(1), 'CS-1'), Fixtures::at(9));
        $this->ledger->receive(Fixtures::consigned(2, '17', ConsignmentTerms::commission(1), 'CS-2'), Fixtures::at(10));
        $this->ledger->receive(Fixtures::purchased(1, 1), Fixtures::at(11));

        self::assertSame([['', 1], ['17', 3]], array_map(static fn (OwnerQuantity $part): array => [$part->owner->key(), $part->quantity], $this->ledger->split(Fixtures::BOOK)));
    }

    public function testAShortageIsRefusedBeforeAnyLayerMoves(): void
    {
        $this->ledger->receive(Fixtures::purchased(1, 1), Fixtures::at(9));

        $this->expectException(ConsignmentRefused::class);
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(1), IssueOrder::OldestFirst, Fixtures::at(10));
    }
}
