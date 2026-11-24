<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Application;

use Phil\CommerceConsignmentBundle\Application\ConfiguredIssueOrder;
use Phil\CommerceConsignmentBundle\Application\ConsignmentStock;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\OwnerQuantity;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryLedgerRepository;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\RecordingStockMovements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsignmentStock::class)]
#[CoversClass(ConfiguredIssueOrder::class)]
final class ConsignmentStockTest extends TestCase
{
    private InMemoryLedgerRepository $repository;

    private RecordingStockMovements $movements;

    private ConsignmentStock $stock;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLedgerRepository();
        $this->movements = new RecordingStockMovements();
        $this->stock = new ConsignmentStock(new Ledger($this->repository), $this->movements, new ConfiguredIssueOrder(IssueOrder::OwnedFirst, ['OTHER' => IssueOrder::ConsignedFirst]));
    }

    public function testAReceiptIsWrittenThenMoved(): void
    {
        $layers = $this->stock->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        self::assertCount(2, $layers);
        self::assertSame(['receive DN-1 at store'], $this->movements->calls);
        self::assertSame([['', 5], [Fixtures::AUTHOR, 5]], $this->split());
    }

    public function testASaleUsesTheIssueOrderOfItsSkuAndMovesNoStock(): void
    {
        $this->stock->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        $sale = $this->stock->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(2000), Fixtures::at(11));

        self::assertSame(2, $sale->quantityOf(Owner::shop()));
        self::assertSame(['receive DN-1 at store'], $this->movements->calls);
    }

    public function testAReturnRestocksUnderTheSalesReference(): void
    {
        $this->stock->receive(Fixtures::mixedReceipt(), Fixtures::at(10));
        $this->stock->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(2000), Fixtures::at(11));

        $return = $this->stock->returnSold('ORD-1', Fixtures::BOOK, Fixtures::BACK, 1, Fixtures::at(12));

        self::assertSame(1, $return->quantity());
        self::assertSame('restock 1 BOOK-1 at back-room (ORD-1)', $this->movements->calls[1]);
    }

    public function testARefusedReturnMovesNoStock(): void
    {
        $this->stock->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        try {
            $this->stock->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::at(12));
            self::fail('Nothing was sold.');
        } catch (ConsignmentRefused) {
            self::assertCount(1, $this->movements->calls);
        }
    }

    public function testATransferMovesTheLedgerThenTheStock(): void
    {
        $this->stock->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        $moved = $this->stock->transfer(Fixtures::BOOK, Fixtures::SHELF, Fixtures::BACK, 6, Fixtures::at(11), 'TR-1');

        self::assertSame([5, 1], [$moved[0]->quantity, $moved[1]->quantity]);
        self::assertSame('transfer 6 BOOK-1 from store to back-room (TR-1)', $this->movements->calls[1]);

        $this->stock->transfer(Fixtures::BOOK, Fixtures::SHELF, Fixtures::BACK, 1, Fixtures::at(12), null, Owner::consignor(Fixtures::AUTHOR));
        self::assertSame([[Fixtures::AUTHOR, 3]], $this->split(Fixtures::SHELF));
        self::assertSame('transfer 1 BOOK-1 from store to back-room (-)', $this->movements->calls[2]);
    }

    public function testAnotherSkuFollowsItsOwnIssueOrder(): void
    {
        $this->stock->receive(Receipt::of('DN-9', Fixtures::SHELF, [
            ReceiptLine::purchased('OTHER', 1, Fixtures::cad(100)),
            ReceiptLine::consigned('OTHER', 1, Fixtures::AUTHOR, ConsignmentTerms::commission(1)),
        ]), Fixtures::at(10));

        $moved = $this->stock->transfer('OTHER', Fixtures::SHELF, Fixtures::BACK, 1, Fixtures::at(11));

        self::assertTrue($moved[0]->owner->isConsigned());
    }

    public function testACountChangesTheStockByTheDifferenceOnly(): void
    {
        $this->stock->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        self::assertSame(0, $this->stock->count(Fixtures::BOOK, Fixtures::SHELF, 10, Fixtures::at(11)));
        self::assertSame(-2, $this->stock->count(Fixtures::BOOK, Fixtures::SHELF, 8, Fixtures::at(11), 'COUNT-1'));
        self::assertSame(1, $this->stock->count(Fixtures::BOOK, Fixtures::SHELF, 6, Fixtures::at(12), null, Owner::consignor(Fixtures::AUTHOR)));
        self::assertSame(['receive DN-1 at store', 'adjust BOOK-1 at store by -2 (COUNT-1)', 'adjust BOOK-1 at store by 1 (-)'], $this->movements->calls);
        self::assertSame([['', 3], [Fixtures::AUTHOR, 6]], $this->split());
    }

    public function testAnAdoptionNeverHoldsMoreThanTheStockHasOnHand(): void
    {
        $this->movements->onHand[Fixtures::BOOK.'@'.Fixtures::SHELF] = 12;
        $this->stock->receive(Fixtures::purchased(2, 1000), Fixtures::at(9));

        $layers = $this->stock->adopt(Fixtures::mixedReceipt('OPEN-1'), Fixtures::at(10));

        self::assertCount(2, $layers);
        self::assertSame(['receive DN-P at store'], $this->movements->calls);
        self::assertSame([EntryKind::Received, EntryKind::Adopted, EntryKind::Adopted], array_map(static fn (LedgerEntry $entry): EntryKind => $entry->kind, $this->repository->journal(Fixtures::BOOK)));

        try {
            $this->stock->adopt(Fixtures::purchased(1, 1000, 'OPEN-2'), Fixtures::at(11));
            self::fail('The stock holds twelve units, all of them owned already.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('The ledger would hold 13 unit(s) of BOOK-1 at store, and the stock has 12 on hand.', $consignmentRefused->getMessage());
        }

        self::assertCount(3, $this->repository->journal(Fixtures::BOOK));
    }

    public function testTheIssueOrderComesFromTheConfiguration(): void
    {
        $policy = ConfiguredIssueOrder::fromConfiguration('consigned_first', ['BOOK' => 'owned_first', 123 => 'oldest_first']);

        self::assertSame(IssueOrder::OwnedFirst, $policy->issueOrder('BOOK'));
        self::assertSame(IssueOrder::OldestFirst, $policy->issueOrder('123'));
        self::assertSame(IssueOrder::ConsignedFirst, $policy->issueOrder('PEN'));
        self::assertSame(IssueOrder::OldestFirst, new ConfiguredIssueOrder()->issueOrder('PEN'));
    }

    /**
     * @return list<array{string, int}>
     */
    private function split(?string $location = null): array
    {
        return array_map(static fn (OwnerQuantity $split): array => [$split->owner->key(), $split->quantity], $this->stock->split(Fixtures::BOOK, $location));
    }
}
