<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\CoreDocumentTypes;
use Phil\CommerceBundle\Application\DocumentRegister;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Document\DeliveryNote;
use Phil\CommerceLogisticsBundle\Application\Document\DeliveryNoteLine;
use Phil\CommerceLogisticsBundle\Application\Document\PickingLine;
use Phil\CommerceLogisticsBundle\Application\Document\PickingSlip;
use Phil\CommerceLogisticsBundle\Application\LogisticsDocumentTypes;
use Phil\CommerceLogisticsBundle\Application\Shipments;
use Phil\CommerceLogisticsBundle\Application\ShippingDocuments;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingDocument;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryDocumentSeries;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShippingDocuments::class)]
#[CoversClass(LogisticsDocumentTypes::class)]
#[CoversClass(ShippingDocument::class)]
#[CoversClass(DeliveryNote::class)]
#[CoversClass(DeliveryNoteLine::class)]
#[CoversClass(PickingSlip::class)]
#[CoversClass(PickingLine::class)]
final class ShippingDocumentsTest extends TestCase
{
    private InMemoryLogistics $repository;

    private Stock $stock;

    private ShippingDocuments $documents;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $this->repository->addLocation(Location::of('store', 'Store'));

        $this->stock = new Stock($this->repository, $this->repository);
        $register = new DocumentRegister([new CoreDocumentTypes(), new LogisticsDocumentTypes()], new InMemoryDocumentSeries());
        $this->documents = new ShippingDocuments($register, $this->repository, $this->repository);
        $this->at = new DateTimeImmutable('2026-11-12T10:00:00Z');
        $this->stock->receive('DICE', 'warehouse', 1, $this->at);
        $this->stock->receive('DICE', 'store', 5, $this->at);
        $this->stock->receive('BAG', 'store', 5, $this->at);
    }

    public function testTheModuleDeclaresItsTwoSeries(): void
    {
        self::assertSame(
            [['delivery_note', 'DN-', 8], ['picking_slip', 'PS-', 8]],
            array_map(static fn (DocumentType $type): array => [$type->code, $type->prefix, $type->digits], [...new LogisticsDocumentTypes()->documentTypes()]),
        );
        self::assertSame(
            ['BL-0001', 'BP-0001'],
            array_map(static fn (DocumentType $type): string => $type->format(1), [...new LogisticsDocumentTypes('BL-', 'BP-', 4)->documentTypes()]),
        );
    }

    public function testAPickingSlipListsTheUnitsHeldLocationByLocationAndTheWrapping(): void
    {
        $order = $this->order(GiftOptions::of(wrapped: true, recycledWrap: true, message: 'Bonne fête!'));
        $this->stock->reserve($order, $this->at);

        $slip = $this->documents->pickingSlip($order, $this->at);

        self::assertSame(['PS-00000001', '2026-0001', true, true], [$slip->number, $slip->orderNumber, $slip->giftWrapped, $slip->recycledWrap]);
        self::assertSame(
            [['BAG', 'store', 1], ['DICE', 'store', 2], ['DICE', 'warehouse', 1], ['MAP', null, 2]],
            array_map(static fn (PickingLine $line): array => [$line->sku, $line->location, $line->quantity], $slip->lines),
        );

        $again = $this->documents->pickingSlip($order, $this->at->modify('+1 hour'));
        self::assertSame(['PS-00000001', $this->at], [$again->number, $again->issuedAt]);
        self::assertCount(1, $this->repository->documents);
    }

    public function testADeliveryNotePrintsTheParcelTheGiftMessageAndThePrices(): void
    {
        $order = $this->order(GiftOptions::of(wrapped: true, message: 'Bonne fête!'));
        $this->stock->reserve($order, $this->at);
        $shipment = new Shipments($this->stock, $this->repository)->ship($order, $this->at, ['product:DICE' => 2, 'product:MAP' => 1], 'Canada Post', 'TRACK-1');

        $note = $this->documents->deliveryNote($order, $shipment, $this->at);

        self::assertSame(['DN-00000001', '2026-0001', $shipment->id, 'Canada Post', 'TRACK-1', true, false, 'Bonne fête!', false], [$note->number, $note->orderNumber, $note->shipment, $note->carrier, $note->tracking, $note->giftWrapped, $note->recycledWrap, $note->giftMessage, $note->pricesHidden]);
        self::assertSame(
            [['product:DICE', 'Dice', 2, 1_000], ['product:MAP', 'Map', 1, 1_000]],
            array_map(static fn (DeliveryNoteLine $line): array => [$line->line, $line->description, $line->quantity, $line->unitPrice?->minorUnits], $note->lines),
        );
        self::assertSame($shipment->id, $this->repository->documents[0]->shipment());

        $second = new Shipments($this->stock, $this->repository)->ship($order, $this->at);
        self::assertSame('DN-00000002', $this->documents->deliveryNote($order, $second, $this->at)->number);
        self::assertSame('DN-00000001', $this->documents->deliveryNote($order, $shipment, $this->at)->number);
    }

    public function testHiddenPricesStayOffTheDeliveryNote(): void
    {
        $order = $this->order(GiftOptions::of(pricesHidden: true));
        $this->stock->reserve($order, $this->at);
        $note = $this->documents->deliveryNote($order, new Shipments($this->stock, $this->repository)->ship($order, $this->at), $this->at);

        self::assertTrue($note->pricesHidden);
        self::assertSame([null, null, null], array_map(static fn (DeliveryNoteLine $line): ?Money => $line->unitPrice, $note->lines));
    }

    public function testAnOrderWithoutGiftOptionsIsNeitherWrappedNorHidden(): void
    {
        $order = $this->order(null);
        $this->stock->reserve($order, $this->at);
        $note = $this->documents->deliveryNote($order, new Shipments($this->stock, $this->repository)->ship($order, $this->at), $this->at);
        $slip = $this->documents->pickingSlip($order, $this->at);

        self::assertSame([false, false, null, false, false, false], [$note->giftWrapped, $note->recycledWrap, $note->giftMessage, $note->pricesHidden, $slip->giftWrapped, $slip->recycledWrap]);
        self::assertSame([['MAP', null, 2]], array_map(static fn (PickingLine $line): array => [$line->sku, $line->location, $line->quantity], $slip->lines));
    }

    public function testAReleasedOrderListsNothingToPick(): void
    {
        $order = $this->order(null);
        $this->stock->reserve($order, $this->at);
        $this->stock->release($order->number, $this->at);

        self::assertSame([['MAP', null, 2]], array_map(static fn (PickingLine $line): array => [$line->sku, $line->location, $line->quantity], $this->documents->pickingSlip($order, $this->at)->lines));
    }

    public function testTheUnitsHeldAndBackorderedAtOneLocationAreOneLine(): void
    {
        $stock = new Stock($this->repository, $this->repository, $this->repository);
        $stock->allowBackorders('DICE');

        $order = Order::open('2026-0002', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'DICE'), 'Dice', $price, 'goods'), 10, $price);
        $order->place($this->at);

        $stock->reserve($order, $this->at);

        self::assertSame(
            [['DICE', 'store', 5], ['DICE', 'warehouse', 5]],
            array_map(static fn (PickingLine $line): array => [$line->sku, $line->location, $line->quantity], $this->documents->pickingSlip($order, $this->at)->lines),
        );
    }

    public function testThePickingLinesFollowTheirLocationsAndTheirSkus(): void
    {
        $this->repository->addLocation(Location::of('store2', 'Store 2'));
        $this->stock->receive('FLAG', 'store2', 1, $this->at);
        $order = Order::open('2026-0003', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));

        foreach (['FLAG', 'ZED', 'BAG', 'ARM', 'DICE'] as $sku) {
            $order->add(Sellable::of(SellableReference::of('product', $sku), $sku, $price, 'goods'), 1, $price);
        }

        $order->place($this->at);
        $this->stock->reserve($order, $this->at);

        self::assertSame(
            [['BAG', 'store', 1], ['FLAG', 'store2', 1], ['DICE', 'warehouse', 1], ['ARM', null, 1], ['ZED', null, 1]],
            array_map(static fn (PickingLine $line): array => [$line->sku, $line->location, $line->quantity], $this->documents->pickingSlip($order, $this->at)->lines),
        );
    }

    public function testAShipmentOfAnotherOrderIsRefused(): void
    {
        $this->expectException(StockRefused::class);

        $this->documents->deliveryNote($this->order(null), Shipment::of('2026-0002', ['product:DICE' => 1], $this->at), $this->at);
    }

    private function order(?GiftOptions $gift): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'DICE'), 'Dice', $price, 'goods'), 3, $price);
        $order->add(Sellable::of(SellableReference::of('product', 'MAP'), 'Map', $price, 'goods'), 2, $price);
        $order->add(Sellable::of(SellableReference::of('product', 'BAG'), 'Bag', $price, 'goods'), 1, $price);
        $order->offerAsGift($gift);
        $order->place($this->at);

        return $order;
    }
}
