<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceSyndicationBundle\Application\Forwarder;
use Phil\CommerceSyndicationBundle\Application\SyndicatedSellables;
use Phil\CommerceSyndicationBundle\Domain\Exception\SyndicationRefused;
use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\SyndicatedProduct;
use Phil\CommerceSyndicationBundle\Domain\ValueObject\FeedItem;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\InMemorySyndication;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Forwarder::class)]
#[CoversClass(SyndicatedSellables::class)]
final class ForwardingTest extends TestCase
{
    private InMemorySyndication $store;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->store = new InMemorySyndication();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->store->addPartner(Partners::supplier());
        $this->store->addPartner(Partners::supplier('other'));
        $this->stock('supplier', 'TEA-1', 700, 3);
        $this->stock('supplier', 'MUG', 400, 1);
        $this->stock('other', 'CUP', 100, 9);
    }

    public function testASyndicatedProductIsSoldAtItsRetailPrice(): void
    {
        $sellables = new SyndicatedSellables($this->store);
        $reference = SyndicatedSellables::reference('supplier', 'TEA-1');

        self::assertSame('syndicated:supplier:TEA-1', $reference->value());
        self::assertTrue($sellables->supports('syndicated'));
        self::assertFalse($sellables->supports('product'));
        $sellable = $sellables->resolve($reference);
        self::assertSame(['Product TEA-1', 1_000, 'food'], [$sellable?->description, $sellable?->unitPrice->minorUnits, $sellable?->taxCategory]);
        self::assertNull($sellables->resolve(SyndicatedSellables::reference('supplier', 'NONE')));
        self::assertNull($sellables->resolve(SellableReference::of('product', 'supplier:TEA-1')));
        self::assertSame(['supplier', ''], SyndicatedSellables::split('supplier'));
        self::assertSame(['supplier', 'A:B:C'], SyndicatedSellables::split('supplier:A:B:C'));
    }

    public function testAProductOutOfStockIsSoldThroughNoChannel(): void
    {
        $sellables = new SyndicatedSellables($this->store);
        $this->stock('supplier', 'GONE', 100, 0);

        self::assertEquals(Offer::full(), $sellables->offerThrough(SyndicatedSellables::reference('supplier', 'TEA-1'), 'web'));
        self::assertEquals(Offer::hidden(), $sellables->offerThrough(SyndicatedSellables::reference('supplier', 'GONE'), null));
        self::assertEquals(Offer::full(), $sellables->offerThrough(SyndicatedSellables::reference('supplier', 'NONE'), null));
        self::assertEquals(Offer::full(), $sellables->offerThrough(SellableReference::of('product', 'supplier:GONE'), null));
    }

    public function testEachSupplierIsSentItsLinesAndOwedTheirCost(): void
    {
        $order = $this->order(['supplier:TEA-1' => 2, 'other:CUP' => 1, 'supplier:MUG' => 3]);

        $forwarded = $this->forwarder()->forward($order, ['name' => 'Ada', 'city' => 'Montréal'], $this->at);

        self::assertSame(['supplier', 'other'], $forwarded);
        self::assertSame([
            ['supplier', 'order.forwarded', '{"order":"A-0001","currency":"CAD","lines":[{"sku":"TEA-1","quantity":2,"unit_cost":700},{"sku":"MUG","quantity":3,"unit_cost":400}],"ship_to":{"name":"Ada","city":"Montréal"}}'],
            ['other', 'order.forwarded', '{"order":"A-0001","currency":"CAD","lines":[{"sku":"CUP","quantity":1,"unit_cost":100}],"ship_to":{"name":"Ada","city":"Montréal"}}'],
        ], array_map(static fn (OutgoingMessage $message): array => [$message->partner, $message->type, json_encode($message->data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)], $this->store->outbox));
        self::assertSame([['supplier', 'A-0001', -2_600], ['other', 'A-0001', -100]], array_map(static fn (LedgerEntry $entry): array => [$entry->partner, $entry->orderNumber, $entry->amount->minorUnits], $this->store->entries));
        self::assertSame([['sku' => 'TEA-1', 'quantity' => 2, 'unit_cost' => 700], ['sku' => 'MUG', 'quantity' => 3, 'unit_cost' => 400]], $this->store->entries[0]->lines);
        self::assertSame([700, 400, null, 3, 0], [$this->store->entries[0]->unitCostOf('TEA-1'), $this->store->entries[0]->unitCostOf('MUG'), $this->store->entries[0]->unitCostOf('CUP'), $this->store->entries[0]->quantityOf('MUG'), $this->store->entries[0]->quantityOf('CUP')]);
    }

    public function testForwardingTwiceSendsNothingMore(): void
    {
        $order = $this->order(['supplier:TEA-1' => 1]);
        $this->forwarder()->forward($order, [], $this->at);

        self::assertSame([], $this->forwarder()->forward($order, [], $this->at));
        self::assertCount(1, $this->store->outbox);
        self::assertSame('{}', json_encode($this->store->outbox[0]->data['ship_to'], \JSON_THROW_ON_ERROR));
    }

    public function testASupplierAlreadyOwedIsSkipped(): void
    {
        $order = $this->order(['supplier:TEA-1' => 1, 'other:CUP' => 2]);
        $this->store->record(LedgerEntry::of(Partners::supplier(), 'A-0001', Money::of(-700, Currency::of('CAD')), $this->at));

        self::assertSame(['other'], $this->forwarder()->forward($order, [], $this->at));
        self::assertSame(['other'], array_map(static fn (OutgoingMessage $message): string => $message->partner, $this->store->outbox));
    }

    public function testAnOrderWithoutSyndicatedLinesIsSentNowhere(): void
    {
        $order = Order::open('A-0002', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'BOOK'), 'Book', Money::of(1_500, Currency::of('CAD')), 'books'), 1, Money::of(1_500, Currency::of('CAD')));
        $order->place($this->at);

        self::assertSame([], $this->forwarder()->forward($order, [], $this->at));
    }

    public function testOnlyAPlacedOrderIsForwarded(): void
    {
        $order = Order::open('A-0003', Currency::of('CAD'));

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The order A-0003 is not placed: only a placed order is forwarded.');

        $this->forwarder()->forward($order, [], $this->at);
    }

    public function testTheSupplierIsAnActiveSupplier(): void
    {
        $this->store->addPartner(Partners::reseller('reseller'));
        $this->stock('reseller', 'TEA-1', 100, 1);

        try {
            $this->forwarder()->forward($this->order(['reseller:TEA-1' => 1]), [], $this->at);
            self::fail('A reseller was sent an order to ship.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('The partner reseller is not a supplier.', $syndicationRefused->getMessage());
        }

        $this->store->partners['other']->suspend();
        $this->expectException(UnknownPartner::class);

        $this->forwarder()->forward($this->order(['other:CUP' => 1], 'A-0009'), [], $this->at);
    }

    public function testAnUnknownSupplierOrProductIsRefused(): void
    {
        try {
            $this->forwarder()->forward($this->order(['nobody:TEA-1' => 1]), [], $this->at);
            self::fail('An unknown supplier was sent an order.');
        } catch (UnknownPartner) {
        }

        $this->store->products = [];
        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The supplier supplier feeds no product TEA-1.');

        $this->forwarder()->forward($this->order(['supplier:TEA-1' => 1], 'A-0010'), [], $this->at);
    }

    private function forwarder(): Forwarder
    {
        return new Forwarder($this->store, $this->store, $this->store, $this->store);
    }

    private function stock(string $supplier, string $sku, int $cost, int $available): void
    {
        $this->store->addProduct(SyndicatedProduct::from(Partners::supplier($supplier), FeedItem::of($sku, 'Product '.$sku, Money::of(1_000, Currency::of('CAD')), Money::of($cost, Currency::of('CAD')), 'food', $available), $this->at));
    }

    /**
     * @param array<string, int> $lines
     */
    private function order(array $lines, string $number = 'A-0001'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));

        foreach ($lines as $id => $quantity) {
            $order->add(Sellable::of(SellableReference::of('syndicated', $id), 'Line '.$id, Money::of(1_000, Currency::of('CAD')), 'food'), $quantity, Money::of(1_000, Currency::of('CAD')));
        }

        $order->place($this->at);

        return $order;
    }
}
