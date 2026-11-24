<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Domain;

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
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Sale;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryLedgerRepository;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ledger::class)]
final class LedgerTest extends TestCase
{
    private InMemoryLedgerRepository $repository;

    private Ledger $ledger;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLedgerRepository();
        $this->ledger = new Ledger($this->repository);
    }

    public function testAReceiptOpensOneLayerPerLineAndSplitsTheSkuByOwner(): void
    {
        $layers = $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        self::assertCount(2, $layers);
        self::assertSame([['', 5], [Fixtures::AUTHOR, 5]], $this->split());
        self::assertSame(10, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF));
        self::assertSame(5, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, Owner::shop()));
        self::assertSame(5, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, Owner::consignor(Fixtures::AUTHOR)));
        self::assertSame(0, $this->ledger->held(Fixtures::BOOK, Fixtures::BACK));
        self::assertSame([], $this->split(Fixtures::BACK));

        $journal = $this->repository->journal(Fixtures::BOOK);
        self::assertSame([EntryKind::Received, EntryKind::Received], array_map(static fn (LedgerEntry $entry): EntryKind => $entry->kind, $journal));
        self::assertSame([5, 5], array_map(static fn (LedgerEntry $entry): int => $entry->quantity, $journal));
        self::assertSame(['DN-1', 'DN-1'], array_map(static fn (LedgerEntry $entry): ?string => $entry->reference, $journal));
        self::assertSame($layers[0]->id, $journal[0]->layer);
        self::assertNull($journal[0]->margin());
    }

    public function testAnAdoptionWritesItsOwnKind(): void
    {
        $this->ledger->receive(Fixtures::purchased(2, 900), Fixtures::at(9), EntryKind::Adopted);

        self::assertSame(EntryKind::Adopted, $this->repository->journal(Fixtures::BOOK)[0]->kind);
    }

    public function testOwnedFirstSellsTheShopsCopiesBeforeTheConsignedOnes(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        $first = $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 3, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));
        self::assertSame(3, $first->quantityOf(Owner::shop()));
        self::assertSame(0, $first->quantityOf(Owner::consignor(Fixtures::AUTHOR)));
        self::assertSame([6000, 3600, 2400], $this->amounts($first));
        self::assertSame([['', 2], [Fixtures::AUTHOR, 5]], $this->split());

        $second = $this->ledger->sell('ORD-2', Fixtures::BOOK, Fixtures::SHELF, 4, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(12));
        self::assertCount(2, $second->lines);
        self::assertSame([2, 2], [$second->lines[0]->quantity, $second->lines[1]->quantity]);
        self::assertTrue($second->lines[1]->owner->isConsigned());
        // 2 × (20 − 12) + 2 × (20 − 15)
        self::assertSame([8000, 5400, 2600], $this->amounts($second));
        self::assertSame([Fixtures::AUTHOR => 3000], array_map(static fn (Money $money): int => $money->minorUnits, $second->owedToConsignors()));
        self::assertSame([[Fixtures::AUTHOR, 3]], $this->split());
    }

    public function testConsignedFirstSellsTheAuthorsCopiesBeforeTheShops(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        $sale = $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 6, Fixtures::cad(2000), IssueOrder::ConsignedFirst, Fixtures::at(11));

        self::assertSame(5, $sale->quantityOf(Owner::consignor(Fixtures::AUTHOR)));
        self::assertSame(1, $sale->quantityOf(Owner::shop()));
        self::assertTrue($sale->lines[0]->owner->isConsigned());
        self::assertSame([12000, 8700, 3300], $this->amounts($sale));
        self::assertSame([['', 4]], $this->split());
    }

    public function testOldestFirstFollowsTheReceiptsWhoeverOwnsThem(): void
    {
        $this->ledger->receive(Fixtures::purchased(5, 1200, 'DN-2'), Fixtures::at(10));
        $this->ledger->receive(Fixtures::consigned(5, Fixtures::AUTHOR, ConsignmentTerms::share(Fixtures::cad(1500)), 'CS-1'), Fixtures::at(9));

        $sale = $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 6, Fixtures::cad(2000), IssueOrder::OldestFirst, Fixtures::at(11));

        self::assertSame([Fixtures::AUTHOR, null], [$sale->lines[0]->owner->consignor, $sale->lines[1]->owner->consignor]);
        self::assertSame([5, 1], [$sale->lines[0]->quantity, $sale->lines[1]->quantity]);
    }

    public function testOldestFirstBetweenTwoReceiptsOfTheSameInstantTakesTheLineWrittenFirst(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        $sale = $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(2000), IssueOrder::OldestFirst, Fixtures::at(11));

        self::assertFalse($sale->lines[0]->owner->isConsigned());
    }

    public function testACommissionRateSplitsThePriceWithoutLosingACent(): void
    {
        $this->ledger->receive(Fixtures::consigned(3, Fixtures::AUTHOR, ConsignmentTerms::commission(300_000)), Fixtures::at(10));

        $sale = $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(1999), IssueOrder::OwnedFirst, Fixtures::at(11));

        // 30 % of 19.99 is 5.997: the shop keeps 6.00, the author 13.99.
        self::assertSame([1999, 1399, 600], $this->amounts($sale));
    }

    public function testASaleOfMoreThanTheLocationHoldsMovesNothing(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        try {
            $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 11, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));
            self::fail('A sale beyond the stock is refused.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('The SKU BOOK-1 holds 10 unit(s) at store in the ledger; 11 were asked.', $consignmentRefused->getMessage());
        }

        self::assertSame([['', 5], [Fixtures::AUTHOR, 5]], $this->split());
        self::assertCount(2, $this->repository->journal(Fixtures::BOOK));
    }

    public function testASaleAtAnotherLocationFindsNothing(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        $this->expectException(ConsignmentRefused::class);
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::BACK, 1, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));
    }

    public function testASaleExactlyEmptiesTheLayers(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        $sale = $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 10, Fixtures::cad(0), IssueOrder::OwnedFirst, Fixtures::at(11));

        self::assertSame(10, $sale->quantity());
        self::assertSame([0, 13500, -13500], $this->amounts($sale));
        self::assertSame([], $this->split());
    }

    public function testASaleRefusesANegativePriceAQuantityAndAReference(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        foreach ([
            static fn (Ledger $ledger): Sale => $ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(-1), IssueOrder::OwnedFirst, Fixtures::at(11)),
            static fn (Ledger $ledger): Sale => $ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 0, Fixtures::cad(100), IssueOrder::OwnedFirst, Fixtures::at(11)),
            static fn (Ledger $ledger): Sale => $ledger->sell('', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(100), IssueOrder::OwnedFirst, Fixtures::at(11)),
        ] as $index => $sale) {
            try {
                $sale($this->ledger);
                self::fail(\sprintf('Case %d is refused.', $index));
            } catch (InvalidValue) {
                self::assertSame(10, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF));
            }
        }

        $this->expectExceptionMessageIsOrContains('A unit price is zero or more; -0.01 is not.');
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(-1), IssueOrder::OwnedFirst, Fixtures::at(11));
    }

    public function testSoldCountsTheSalesOfAReferenceByLocation(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));
        $this->ledger->receive(Fixtures::purchased(4, 1000, 'DN-B', Fixtures::BACK), Fixtures::at(10));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 7, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::BACK, 2, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));
        $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::at(12));

        self::assertSame(9, $this->ledger->sold('ORD-1', Fixtures::BOOK));
        self::assertSame(7, $this->ledger->sold('ORD-1', Fixtures::BOOK, Fixtures::SHELF));
        self::assertSame(2, $this->ledger->sold('ORD-1', Fixtures::BOOK, Fixtures::BACK));
        self::assertSame(0, $this->ledger->sold('ORD-2', Fixtures::BOOK));
    }

    public function testAReturnGoesBackToTheOwnersTheUnitsSoldLastFirst(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 7, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));

        $return = $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 3, Fixtures::at(12));

        self::assertSame('ORD-1', $return->reference);
        self::assertSame([Fixtures::AUTHOR, null], [$return->lines[0]->owner->consignor, $return->lines[1]->owner->consignor]);
        self::assertSame([2, 1], [$return->lines[0]->quantity, $return->lines[1]->quantity]);
        // −(2 × 20 − 2 × 15) and −(20 − 12)
        self::assertSame([-6000, -4200, -1800], $this->amounts($return));
        self::assertSame([['', 1], [Fixtures::AUTHOR, 5]], $this->split());

        $entries = $this->repository->entriesOf('ORD-1', Fixtures::BOOK);
        $returned = array_values(array_filter($entries, static fn (LedgerEntry $entry): bool => EntryKind::Returned === $entry->kind));
        self::assertSame([2, 1], array_map(static fn (LedgerEntry $entry): int => $entry->quantity, $returned));
        self::assertSame(2000, $returned[0]->unitPrice()?->minorUnits);
        self::assertSame(-1000, $returned[0]->margin()?->margin->minorUnits);
    }

    public function testAReturnBeyondWhatWasSoldIsRefused(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 4, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));
        $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 3, Fixtures::at(12));

        try {
            $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::at(13));
            self::fail('Only one unit is left to return.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('The sale ORD-1 left 1 unit(s) of BOOK-1 that are not returned yet; 2 were brought back.', $consignmentRefused->getMessage());
        }

        $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::at(13));
        self::assertSame([['', 5], [Fixtures::AUTHOR, 5]], $this->split());

        $this->expectException(ConsignmentRefused::class);
        $this->ledger->returnSold('ORD-9', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::at(13));
    }

    public function testAReturnRefusesNoUnitAndABadLocation(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 4, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));

        foreach ([[Fixtures::SHELF, 0], ['', 1]] as [$location, $quantity]) {
            try {
                $this->ledger->returnSold('ORD-1', Fixtures::BOOK, $location, $quantity, Fixtures::at(12));
                self::fail('The return is refused.');
            } catch (InvalidValue) {
                self::assertSame(4, $this->ledger->sold('ORD-1', Fixtures::BOOK));
            }
        }

        self::assertSame([['', 1], [Fixtures::AUTHOR, 5]], $this->split());
    }

    public function testAReturnAtAnotherLocationSplitsALayerKeepingTheCost(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));
        $sale = $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));

        $first = $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::BACK, 1, Fixtures::at(12));
        $second = $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::BACK, 1, Fixtures::at(13));

        self::assertSame([['', 2]], $this->split(Fixtures::BACK));
        self::assertSame($first->lines[0]->layer, $second->lines[0]->layer);
        self::assertNotSame($sale->lines[0]->layer, $first->lines[0]->layer);
        self::assertSame(Fixtures::BACK, $first->lines[0]->location);
        $layer = $this->repository->layer($first->lines[0]->layer);
        self::assertInstanceOf(StockLayer::class, $layer);
        self::assertSame(1200, $layer->unitCost()?->minorUnits);
        self::assertSame([-2000, -1200, -800], $this->amounts($second));
        self::assertSame(0, $this->returnableAfter('ORD-1'));
    }

    public function testAReturnAtItsOwnLocationRefillsTheLayer(): void
    {
        $this->ledger->receive(Fixtures::purchased(3, 1000), Fixtures::at(10));
        $sale = $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 3, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));

        $return = $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::at(12));

        self::assertSame($sale->lines[0]->layer, $return->lines[0]->layer);
        self::assertSame(2, $this->repository->layer($sale->lines[0]->layer)?->remaining());
    }

    public function testAReturnOfASaleAtTwoPricesGivesBackEachPrice(): void
    {
        $this->ledger->receive(Fixtures::purchased(4, 1000), Fixtures::at(10));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(1500), IssueOrder::OwnedFirst, Fixtures::at(11, 30));

        $return = $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::at(12));

        self::assertSame([1, 1], [$return->lines[0]->quantity, $return->lines[1]->quantity]);
        self::assertSame([-3500, -2000, -1500], $this->amounts($return));
    }

    public function testATransferMovesUnitsInTheIssueOrderKeepingTheirOwners(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        $moved = $this->ledger->transfer(Fixtures::BOOK, Fixtures::SHELF, Fixtures::BACK, 6, IssueOrder::OwnedFirst, Fixtures::at(11), 'TR-1');

        self::assertSame([5, 1], [$moved[0]->quantity, $moved[1]->quantity]);
        self::assertSame([Fixtures::BACK, Fixtures::BACK], [$moved[0]->location, $moved[1]->location]);
        self::assertSame([['', 5], [Fixtures::AUTHOR, 1]], $this->split(Fixtures::BACK));
        self::assertSame([[Fixtures::AUTHOR, 4]], $this->split(Fixtures::SHELF));
        self::assertSame([['', 5], [Fixtures::AUTHOR, 5]], $this->split());

        $kinds = array_map(static fn (LedgerEntry $entry): string => $entry->kind->value.' '.$entry->quantity.' '.$entry->location.' '.($entry->reference ?? '-'), \array_slice($this->repository->journal(Fixtures::BOOK), 2));
        self::assertSame([
            'transferred_out -5 store TR-1',
            'transferred_in 5 back-room TR-1',
            'transferred_out -1 store TR-1',
            'transferred_in 1 back-room TR-1',
        ], $kinds);
    }

    public function testATransferOfOneOwnerRefillsTheLayerAlreadyThere(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));
        $first = $this->ledger->transfer(Fixtures::BOOK, Fixtures::SHELF, Fixtures::BACK, 1, IssueOrder::ConsignedFirst, Fixtures::at(11));

        $second = $this->ledger->transfer(Fixtures::BOOK, Fixtures::SHELF, Fixtures::BACK, 2, IssueOrder::OwnedFirst, Fixtures::at(12), null, Owner::consignor(Fixtures::AUTHOR));

        self::assertSame($first[0]->layer, $second[0]->layer);
        self::assertSame([[Fixtures::AUTHOR, 3]], $this->split(Fixtures::BACK));
        $origin = $this->repository->layer($first[0]->layer)?->origin;
        self::assertIsString($origin);
        self::assertCount(2, $this->repository->layersFrom($origin));

        $back = $this->ledger->transfer(Fixtures::BOOK, Fixtures::BACK, Fixtures::SHELF, 3, IssueOrder::OwnedFirst, Fixtures::at(13));
        self::assertSame($origin, $back[0]->layer);
        self::assertSame([['', 5], [Fixtures::AUTHOR, 5]], $this->split(Fixtures::SHELF));
    }

    public function testATransferOfAnOwnerWithTooFewUnitsMovesNothing(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        try {
            $this->ledger->transfer(Fixtures::BOOK, Fixtures::SHELF, Fixtures::BACK, 6, IssueOrder::OwnedFirst, Fixtures::at(11), null, Owner::shop());
            self::fail('The shop holds five units.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('The SKU BOOK-1 holds 5 unit(s) at store in the ledger; 6 were asked.', $consignmentRefused->getMessage());
        }

        self::assertSame([], $this->split(Fixtures::BACK));
    }

    public function testATransferRefusesTheSameLocationAndBadValues(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        try {
            $this->ledger->transfer(Fixtures::BOOK, Fixtures::SHELF, Fixtures::SHELF, 1, IssueOrder::OwnedFirst, Fixtures::at(11));
            self::fail('A transfer goes elsewhere.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('A transfer goes from one location to another, not from store to itself.', $consignmentRefused->getMessage());
        }

        foreach ([['', 1], [Fixtures::BACK, 0]] as [$to, $quantity]) {
            try {
                $this->ledger->transfer(Fixtures::BOOK, Fixtures::SHELF, $to, $quantity, IssueOrder::OwnedFirst, Fixtures::at(11));
                self::fail('The transfer is refused.');
            } catch (InvalidValue) {
                self::assertSame(10, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF));
            }
        }
    }

    public function testACountThatFindsFewerUnitsTakesThemFromTheShopFirst(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        self::assertSame(-6, $this->ledger->count(Fixtures::BOOK, Fixtures::SHELF, 4, Fixtures::at(11), 'COUNT-1'));
        self::assertSame([[Fixtures::AUTHOR, 4]], $this->split());

        $last = $this->repository->journal(Fixtures::BOOK);
        self::assertSame([EntryKind::Counted, -5, 'COUNT-1'], [$last[2]->kind, $last[2]->quantity, $last[2]->reference]);
        self::assertSame([EntryKind::Counted, -1], [$last[3]->kind, $last[3]->quantity]);
    }

    public function testACountThatFindsWhatTheLedgerHoldsWritesNothing(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        self::assertSame(0, $this->ledger->count(Fixtures::BOOK, Fixtures::SHELF, 10, Fixtures::at(11)));
        self::assertCount(2, $this->repository->journal(Fixtures::BOOK));
    }

    public function testACountThatFindsMoreUnitsGivesThemToTheShopsNewestLayer(): void
    {
        $this->ledger->receive(Fixtures::purchased(2, 1000, 'DN-1'), Fixtures::at(9));
        $this->ledger->receive(Fixtures::purchased(2, 1100, 'DN-2'), Fixtures::at(10));
        $this->ledger->receive(Fixtures::consigned(2, Fixtures::AUTHOR, ConsignmentTerms::commission(250_000)), Fixtures::at(11));

        self::assertSame(3, $this->ledger->count(Fixtures::BOOK, Fixtures::SHELF, 9, Fixtures::at(12)));

        self::assertSame([['', 7], [Fixtures::AUTHOR, 2]], $this->split());
        $newest = array_values(array_filter($this->repository->layersAt(Fixtures::BOOK, Fixtures::SHELF), static fn (StockLayer $layer): bool => 'DN-2' === $layer->reference));
        self::assertSame(5, $newest[0]->remaining());
    }

    public function testACountOfOneOwnerChangesOnlyThatOwner(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(10));
        $author = Owner::consignor(Fixtures::AUTHOR);

        self::assertSame(-1, $this->ledger->count(Fixtures::BOOK, Fixtures::SHELF, 4, Fixtures::at(11), null, $author));
        self::assertSame([['', 5], [Fixtures::AUTHOR, 4]], $this->split());

        self::assertSame(3, $this->ledger->count(Fixtures::BOOK, Fixtures::SHELF, 7, Fixtures::at(12), null, $author));
        self::assertSame([['', 5], [Fixtures::AUTHOR, 7]], $this->split());
    }

    public function testACountElsewhereSplitsTheOwnersLastReceipt(): void
    {
        $this->ledger->receive(Fixtures::purchased(2, 1000, 'DN-1'), Fixtures::at(9));
        $this->ledger->receive(Fixtures::purchased(2, 1100, 'DN-2'), Fixtures::at(10));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 4, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(11));

        self::assertSame(3, $this->ledger->count(Fixtures::BOOK, Fixtures::BACK, 3, Fixtures::at(12)));

        $found = $this->repository->layersAt(Fixtures::BOOK, Fixtures::BACK);
        self::assertCount(1, $found);
        self::assertSame(1100, $found[0]->unitCost()?->minorUnits);
        self::assertSame(3, $found[0]->remaining());
    }

    public function testACountCannotFindUnitsOfAnOwnerWhoNeverHeldTheSku(): void
    {
        $this->ledger->receive(Fixtures::purchased(2, 1000), Fixtures::at(9));

        try {
            $this->ledger->count(Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::at(10), null, Owner::consignor('artisan-bo'));
            self::fail('Nothing tells the terms of the units found.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('A count found more units of BOOK-1 than the consignor artisan-bo holds, and the consignor artisan-bo never received it: receive the units instead, with their cost or their terms.', $consignmentRefused->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('A count finds zero units or more; -1 is not.');
        $this->ledger->count(Fixtures::BOOK, Fixtures::SHELF, -1, Fixtures::at(10));
    }

    public function testTheSplitListsTheShopThenTheConsignorsByIdentifier(): void
    {
        $this->ledger->receive(Fixtures::consigned(1, 'zoe', ConsignmentTerms::commission(100_000), 'CS-Z'), Fixtures::at(9));
        $this->ledger->receive(Fixtures::consigned(2, '42', ConsignmentTerms::commission(100_000), 'CS-4'), Fixtures::at(9));
        $this->ledger->receive(Fixtures::purchased(3, 900), Fixtures::at(10));
        $this->ledger->receive(Fixtures::consigned(4, 'zoe', ConsignmentTerms::commission(100_000), 'CS-Z2', Fixtures::BACK), Fixtures::at(11));

        self::assertSame([['', 3], ['42', 2], ['zoe', 5]], $this->split());
        self::assertSame([['', 3], ['42', 2], ['zoe', 1]], $this->split(Fixtures::SHELF));
    }

    /**
     * @return list<array{string, int}>
     */
    private function split(?string $location = null): array
    {
        return array_map(static fn (OwnerQuantity $split): array => [$split->owner->key(), $split->quantity], $this->ledger->split(Fixtures::BOOK, $location));
    }

    /**
     * @return array{int, int, int} the revenue, the cost or share, the margin
     */
    private function amounts(Sale $sale): array
    {
        $margin = $sale->margin();
        self::assertNotNull($margin);

        return [$margin->revenue->minorUnits, $margin->cost->minorUnits, $margin->margin->minorUnits];
    }

    private function returnableAfter(string $reference): int
    {
        $left = 0;

        foreach ($this->repository->entriesOf($reference, Fixtures::BOOK) as $entry) {
            $left -= $entry->quantity;
        }

        return $left;
    }
}
