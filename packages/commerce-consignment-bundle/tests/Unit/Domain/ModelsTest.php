<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Domain;

use DateTimeZone;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Margin;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StockLayer::class)]
#[CoversClass(LedgerEntry::class)]
#[CoversClass(IssueOrder::class)]
#[CoversClass(ConsignmentRefused::class)]
final class ModelsTest extends TestCase
{
    public function testAPurchasedLayerKeepsItsCostInUtc(): void
    {
        $layer = StockLayer::receive(ReceiptLine::purchased('BOOK', 5, Fixtures::cad(1200)), 'store', 'DN-1', Fixtures::at(10));

        self::assertSame($layer->id, $layer->origin);
        self::assertSame(['BOOK', 'store', 'DN-1', 5, 5, 1], [$layer->sku, $layer->location, $layer->reference, $layer->received, $layer->remaining(), $layer->version()]);
        self::assertFalse($layer->owner()->isConsigned());
        self::assertSame(1200, $layer->unitCost()?->minorUnits);
        self::assertSame('CAD', $layer->unitCost()->currency->code);
        self::assertNull($layer->terms());
        self::assertSame('2026-11-20 15:00:00 UTC', $layer->receivedAt()->format('Y-m-d H:i:s T'));
    }

    public function testAConsignedLayerKeepsItsTerms(): void
    {
        $share = StockLayer::receive(ReceiptLine::consigned('BOOK', 5, 'ann', ConsignmentTerms::share(Fixtures::cad(1500))), 'store', 'CS-1', Fixtures::at(10));
        $rate = StockLayer::receive(ReceiptLine::consigned('BOOK', 2, 'bo', ConsignmentTerms::commission(300_000)), 'store', 'CS-2', Fixtures::at(10));

        self::assertSame('ann', $share->owner()->consignor);
        self::assertNull($share->unitCost());
        self::assertSame(1500, $share->terms()?->share?->minorUnits);
        self::assertSame('CAD', $share->terms()->share->currency->code);
        self::assertNull($rate->terms()?->share);
        self::assertSame(300_000, $rate->terms()?->commissionPpm);
    }

    public function testACopyElsewhereKeepsEverythingButItsPlaceAndQuantity(): void
    {
        $layer = StockLayer::receive(ReceiptLine::consigned('BOOK', 5, 'ann', ConsignmentTerms::share(Fixtures::cad(1500))), 'store', 'CS-1', Fixtures::at(10));

        $copy = $layer->copyAt('back-room', 2);

        self::assertNotSame($layer->id, $copy->id);
        self::assertSame([$layer->origin, 'BOOK', 'back-room', 'CS-1', 2, 2], [$copy->origin, $copy->sku, $copy->location, $copy->reference, $copy->received, $copy->remaining()]);
        self::assertSame('ann', $copy->owner()->consignor);
        self::assertSame(1500, $copy->terms()?->share?->minorUnits);
        self::assertSame($layer->receivedAt(), $copy->receivedAt());
        self::assertSame(5, $layer->remaining());

        $this->expectException(InvalidValue::class);
        $layer->copyAt('back-room', 0);
    }

    public function testALayerGivesAndTakesBackUnits(): void
    {
        $layer = StockLayer::receive(ReceiptLine::purchased('BOOK', 5, Fixtures::cad(1200)), 'store', 'DN-1', Fixtures::at(10));

        $layer->take(5);
        self::assertSame(0, $layer->remaining());
        $layer->restock(2);
        self::assertSame(2, $layer->remaining());

        try {
            $layer->take(3);
            self::fail('The layer holds two units.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('The SKU BOOK holds 2 unit(s) at store in the ledger; 3 were asked.', $consignmentRefused->getMessage());
        }

        foreach ([static fn () => $layer->take(0), static fn () => $layer->restock(0)] as $call) {
            try {
                $call();
                self::fail('A layer moves one unit or more.');
            } catch (InvalidValue) {
                self::assertSame(2, $layer->remaining());
            }
        }

        $this->expectException(InvalidValue::class);
        StockLayer::receive(ReceiptLine::purchased('BOOK', 5, Fixtures::cad(1200)), '', 'DN-1', Fixtures::at(10));
    }

    public function testAnEntryOfAMovementCarriesNoMoney(): void
    {
        $layer = StockLayer::receive(ReceiptLine::consigned('BOOK', 5, 'ann', ConsignmentTerms::commission(300_000)), 'store', 'CS-1', Fixtures::at(10));

        $entry = LedgerEntry::movement($layer, EntryKind::TransferredOut, -2, null, Fixtures::at(11));

        self::assertSame([$layer->id, 'BOOK', 'store', EntryKind::TransferredOut, -2, null], [$entry->layer, $entry->sku, $entry->location, $entry->kind, $entry->quantity, $entry->reference]);
        self::assertSame('ann', $entry->owner()->consignor);
        self::assertSame('2026-11-20 16:00:00 UTC', $entry->movedAt()->format('Y-m-d H:i:s T'));
        self::assertNull($entry->unitPrice());
        self::assertNull($entry->margin());
    }

    public function testAnEntryOfASaleCarriesItsMoney(): void
    {
        $layer = StockLayer::receive(ReceiptLine::purchased('BOOK', 5, Fixtures::cad(1200)), 'store', 'DN-1', Fixtures::at(10));

        $entry = LedgerEntry::sale($layer, EntryKind::Sold, -2, 'ORD-1', Fixtures::cad(2000), Margin::of(Fixtures::cad(4000), Fixtures::cad(2400)), Fixtures::at(11));

        self::assertSame('', $entry->owner()->key());
        self::assertSame(2000, $entry->unitPrice()?->minorUnits);
        self::assertSame('CAD', $entry->unitPrice()->currency->code);
        self::assertSame([4000, 2400, 1600], [$entry->margin()?->revenue->minorUnits, $entry->margin()?->cost->minorUnits, $entry->margin()?->margin->minorUnits]);
        self::assertSame('CAD', $entry->margin()?->revenue->currency->code);
        self::assertEquals(new DateTimeZone('UTC'), $entry->movedAt()->getTimezone());
    }

    public function testTheIssueOrdersSortTheLayers(): void
    {
        $old = StockLayer::receive(ReceiptLine::consigned('BOOK', 1, 'ann', ConsignmentTerms::commission(1)), 'store', 'CS-1', Fixtures::at(8));
        $bought = StockLayer::receive(ReceiptLine::purchased('BOOK', 1, Fixtures::cad(1)), 'store', 'DN-1', Fixtures::at(9));
        $young = StockLayer::receive(ReceiptLine::consigned('BOOK', 1, 'bo', ConsignmentTerms::commission(1)), 'store', 'CS-2', Fixtures::at(10));
        $boughtLater = StockLayer::receive(ReceiptLine::purchased('BOOK', 1, Fixtures::cad(1)), 'store', 'DN-2', Fixtures::at(11));
        $layers = [$boughtLater, $young, $bought, $old];
        $references = static fn (array $sorted): array => array_map(static fn (StockLayer $layer): ?string => $layer->reference, $sorted);

        self::assertSame(['DN-1', 'DN-2', 'CS-1', 'CS-2'], $references(IssueOrder::OwnedFirst->sort($layers)));
        self::assertSame(['CS-1', 'CS-2', 'DN-1', 'DN-2'], $references(IssueOrder::ConsignedFirst->sort($layers)));
        self::assertSame(['CS-1', 'DN-1', 'CS-2', 'DN-2'], $references(IssueOrder::OldestFirst->sort($layers)));
    }

    public function testLayersOfOneInstantAreOrderedByTheirReceiptThenByThemselves(): void
    {
        $first = StockLayer::receive(ReceiptLine::purchased('BOOK', 2, Fixtures::cad(1)), 'store', 'DN-1', Fixtures::at(9));
        $second = StockLayer::receive(ReceiptLine::purchased('BOOK', 2, Fixtures::cad(1)), 'store', 'DN-2', Fixtures::at(9));
        $copy = $first->copyAt('back-room', 1);

        self::assertSame(-1, IssueOrder::age($first, $second));
        self::assertSame(1, IssueOrder::age($second, $first));
        self::assertSame(-1, IssueOrder::age($first, $copy));
        self::assertSame(-1, IssueOrder::age($copy, $second));
        self::assertSame(0, IssueOrder::age($first, $first));
        self::assertSame(1, IssueOrder::age(StockLayer::receive(ReceiptLine::purchased('BOOK', 2, Fixtures::cad(1)), 'store', 'DN-0', Fixtures::at(10)), $second));
    }

    public function testTheRefusalsSayWhatHappened(): void
    {
        self::assertSame('The ledger would hold 7 unit(s) of BOOK at store, and the stock has 5 on hand.', ConsignmentRefused::beyondOnHand('BOOK', 'store', 7, 5)->getMessage());
        self::assertSame('The purchase order PO-1 is received at store, not at back-room.', ConsignmentRefused::purchaseOrderElsewhere('PO-1', 'store', 'back-room')->getMessage());
        self::assertSame('The order ORD-1 shipped 3 unit(s) of BOOK and holds 2.', ConsignmentRefused::notOrdered('ORD-1', 'BOOK', 3, 2)->getMessage());
    }
}
