<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Application\ForwardedOrder;
use Phil\CommerceSyndicationBundle\Application\Inbox;
use Phil\CommerceSyndicationBundle\Application\PartnerKey;
use Phil\CommerceSyndicationBundle\Domain\Exception\SyndicationRefused;
use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\InMemorySyndication;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\RecordingHandler;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\StandardWebhooks\Ed25519PublicKey;
use Phil\StandardWebhooks\Ed25519SecretKey;
use Phil\StandardWebhooks\Exception\WebhookRejected;
use Phil\StandardWebhooks\Signer;
use Phil\StandardWebhooks\WebhookHeaders;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooks\WebhookTimestamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Inbox::class)]
#[CoversClass(PartnerKey::class)]
#[CoversClass(ForwardedOrder::class)]
final class InboxTest extends TestCase
{
    private InMemorySyndication $store;

    private DateTimeImmutable $at;

    private RecordingHandler $handler;

    protected function setUp(): void
    {
        $this->store = new InMemorySyndication();
        $this->handler = new RecordingHandler();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->store->addPartner(Partners::supplier());
        $this->store->addPartner(Partners::reseller('reseller', ['TEA-1', 'MUG']));
        $this->publish('TEA-1', 700, '-30 days');
        $this->publish('MUG', 1_100, '-30 days');
    }

    public function testASupplierDescribesItsProduct(): void
    {
        self::assertTrue($this->receive('supplier', Partners::supplierKey(), 'msg_1', $this->product(5), '2026-11-03T09:59:59.000001+00:00'));

        $product = $this->store->findProduct('supplier', 'TEA-1');
        self::assertSame(['Green tea', 1_000, 700, 'CAD', 'food', 5, true, '2026-11-03T09:59:59.000001+00:00'], [
            $product?->name(), $product?->retail->minorUnits, $product?->cost->minorUnits, $product?->retail->currency->code, $product?->taxCategory(), $product?->available(), $product?->isSold(), $product?->describedAt()->format('Y-m-d\TH:i:s.uP'),
        ]);
        self::assertSame(['supplier', 'product.updated'], [$this->store->received['supplier msg_1']->partner, $this->store->received['supplier msg_1']->type]);
    }

    public function testALaterMessageUpdatesTheProductAndAnEarlierOneDoesNot(): void
    {
        $this->receive('supplier', Partners::supplierKey(), 'msg_1', $this->product(5), '2026-11-03T09:00:00.000000+00:00');
        $this->receive('supplier', Partners::supplierKey(), 'msg_2', $this->product(3), '2026-11-03T09:00:00.000001+00:00');
        $this->receive('supplier', Partners::supplierKey(), 'msg_3', $this->product(9), '2026-11-03T08:00:00.000000+00:00');

        self::assertSame(3, $this->store->findProduct('supplier', 'TEA-1')?->available());
        self::assertCount(3, $this->store->received);
    }

    public function testAMessageReceivedTwiceIsAppliedOnce(): void
    {
        $this->receive('supplier', Partners::supplierKey(), 'msg_1', $this->product(5), '2026-11-03T09:00:00.000000+00:00');

        self::assertFalse($this->receive('supplier', Partners::supplierKey(), 'msg_1', $this->product(1), '2026-11-03T09:30:00.000000+00:00'));
        self::assertSame(5, $this->store->findProduct('supplier', 'TEA-1')?->available());
    }

    public function testAResellersOrderIsOwedAndHandled(): void
    {
        self::assertTrue($this->receive('reseller', Partners::resellerKey(), 'msg_9', 'order.forwarded', null, $this->order()));

        self::assertSame([['reseller', 'R-77', 2_500, 'CAD']], array_map(static fn (LedgerEntry $entry): array => [$entry->partner, $entry->orderNumber, $entry->amount->minorUnits, $entry->amount->currency->code], $this->store->entries));
        self::assertSame([['sku' => 'TEA-1', 'quantity' => 2, 'unit_cost' => 700], ['sku' => 'MUG', 'quantity' => 1, 'unit_cost' => 1_100]], $this->store->entries[0]->lines);
        self::assertCount(1, $this->handler->handled);
        $order = $this->handler->handled[0];
        self::assertSame(['reseller', 'R-77', ['name' => 'Ada', 0 => 'x'], 2_500], [$order->reseller, $order->orderNumber, $order->shipTo, $order->owed->minorUnits]);
        self::assertSame([['TEA-1', 2, 700], ['MUG', 1, 1_100]], array_map(static fn (array $line): array => [$line['sku'], $line['quantity'], $line['unitCost']->minorUnits], $order->lines));
    }

    public function testAnOrderAlreadyOwedIsNotHandledAgain(): void
    {
        $this->receive('reseller', Partners::resellerKey(), 'msg_9', 'order.forwarded', null, $this->order());
        $this->receive('reseller', Partners::resellerKey(), 'msg_10', 'order.forwarded', null, $this->order());

        self::assertCount(1, $this->store->entries);
        self::assertCount(1, $this->handler->handled);
        self::assertCount(2, $this->store->received);
    }

    public function testAnUnknownTypeIsAcknowledged(): void
    {
        self::assertTrue($this->receive('reseller', Partners::resellerKey(), 'msg_1', 'stock.counted', null, ['sku' => 'TEA-1']));
        self::assertSame('stock.counted', $this->store->received['reseller msg_1']->type);
    }

    public function testAnUnknownOrSuspendedPartnerIsRefused(): void
    {
        try {
            $this->receive('nobody', Partners::supplierKey(), 'msg_1', $this->product(1), '2026-11-03T09:00:00.000000+00:00');
            self::fail('An unknown partner was heard.');
        } catch (UnknownPartner) {
        }

        $this->store->partners['supplier']->suspend();
        $this->expectException(UnknownPartner::class);

        $this->receive('supplier', Partners::supplierKey(), 'msg_1', $this->product(1), '2026-11-03T09:00:00.000000+00:00');
    }

    public function testAMessageSignedByAnotherPartnerIsRefused(): void
    {
        $this->expectException(WebhookRejected::class);

        $this->receive('supplier', Partners::resellerKey(), 'msg_1', $this->product(1), '2026-11-03T09:00:00.000000+00:00');
    }

    public function testAMessageAddressedElsewhereIsRefused(): void
    {
        $this->expectException(WebhookRejected::class);

        $this->receive('supplier', Partners::supplierKey(), 'msg_1', $this->product(1), '2026-11-03T09:00:00.000000+00:00', null, 'someone-else');
    }

    public function testAStaleMessageIsRefused(): void
    {
        $this->expectException(WebhookRejected::class);

        $this->receive('supplier', Partners::supplierKey(), 'msg_1', $this->product(1), '2026-11-03T09:00:00.000000+00:00', null, 'shop', $this->at->modify('-301 seconds'));
    }

    public function testEachPartnerSendsItsOwnMessages(): void
    {
        try {
            $this->receive('reseller', Partners::resellerKey(), 'msg_1', $this->product(1), '2026-11-03T09:00:00.000000+00:00');
            self::fail('A reseller described a product.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('The partner reseller is not a supplier.', $syndicationRefused->getMessage());
        }

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The partner supplier is not a reseller.');

        $this->receive('supplier', Partners::supplierKey(), 'msg_2', 'order.forwarded', null, $this->order());
    }

    public function testAProductUpdateCarriesItsMoment(): void
    {
        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The "product.updated" message cannot be read: its envelope carries the moment of the change.');

        $this->receive('supplier', Partners::supplierKey(), 'msg_1', $this->product(1), null);
    }

    public function testTheMomentHasItsFormat(): void
    {
        $this->expectException(SyndicationRefused::class);

        $this->receive('supplier', Partners::supplierKey(), 'msg_1', $this->product(1), '2026-11-03 09:00:00');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function unreadableProducts(): iterable
    {
        $product = ['sku' => 'TEA-1', 'name' => 'Green tea', 'currency' => 'CAD', 'retail' => 1_000, 'cost' => 700, 'tax_category' => 'food', 'available' => 1, 'active' => true];

        yield 'no SKU' => [['sku' => 1] + $product, '"sku" is a string'];
        yield 'a decimal price' => [['retail' => '10.00'] + $product, '"retail" is an integer'];
        yield 'a word for active' => [['active' => 'yes'] + $product, '"active" is a boolean'];
        yield 'an unknown currency' => [['currency' => 'XYZ'] + $product, 'XYZ'];
        yield 'a cost above the price' => [['cost' => 2_000] + $product, 'The product TEA-1 costs zero or more'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('unreadableProducts')]
    public function testAnUnreadableProductIsRefused(array $data, string $reason): void
    {
        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The "product.updated" message cannot be read: ');
        $this->expectExceptionMessageIsOrContains($reason);

        $this->receive('supplier', Partners::supplierKey(), 'msg_1', 'product.updated', '2026-11-03T09:00:00.000000+00:00', $data);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function unreadableOrders(): iterable
    {
        $order = ['order' => 'R-77', 'currency' => 'CAD', 'lines' => [['sku' => 'TEA-1', 'quantity' => 1, 'unit_cost' => 700]], 'ship_to' => []];

        yield 'no lines' => [['lines' => []] + $order, 'it carries a list of lines and an object "ship_to"'];
        yield 'lines as an object' => [['lines' => ['a' => ['sku' => 'TEA-1']]] + $order, 'it carries a list of lines and an object "ship_to"'];
        yield 'an address as a string' => [['ship_to' => 'Montréal'] + $order, 'it carries a list of lines and an object "ship_to"'];
        yield 'a line as a string' => [['lines' => ['TEA-1']] + $order, 'each line is an object'];
        yield 'no quantity' => [['lines' => [['sku' => 'TEA-1', 'unit_cost' => 700]]] + $order, '"quantity" is an integer'];
        yield 'no unit' => [['lines' => [['sku' => 'TEA-1', 'quantity' => 0, 'unit_cost' => 700]]] + $order, 'a line holds one unit or more, at a cost of zero or more'];
        yield 'a negative cost' => [['lines' => [['sku' => 'TEA-1', 'quantity' => 1, 'unit_cost' => -1]]] + $order, 'a line holds one unit or more, at a cost of zero or more'];
        yield 'no number' => [['order' => null] + $order, '"order" is a string'];
        yield 'an empty number' => [['order' => ''] + $order, 'its order number is 1 to 64 visible ASCII characters'];
        yield 'a long number' => [['order' => str_repeat('R', 65)] + $order, 'its order number is 1 to 64 visible ASCII characters'];
        yield 'a spaced number' => [['order' => 'R 77'] + $order, 'its order number is 1 to 64 visible ASCII characters'];
        yield 'a number ending in a new line' => [['order' => "R-77\n"] + $order, 'its order number is 1 to 64 visible ASCII characters'];
        yield 'a total that overflows' => [['lines' => [['sku' => 'TEA-1', 'quantity' => \PHP_INT_MAX, 'unit_cost' => 700]]] + $order, 'The amount leaves the range of a native integer.'];
        yield 'an unknown currency' => [['currency' => 'XYZ'] + $order, 'XYZ'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('unreadableOrders')]
    public function testAnUnreadableOrderIsRefused(array $data, string $reason): void
    {
        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The "order.forwarded" message cannot be read: ');
        $this->expectExceptionMessageIsOrContains($reason);

        $this->receive('reseller', Partners::resellerKey(), 'msg_1', 'order.forwarded', null, $data);
    }

    public function testAResellerOrdersOnlyWhatItIsFed(): void
    {
        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The reseller reseller is not fed the product CUP.');

        $this->receive('reseller', Partners::resellerKey(), 'msg_1', 'order.forwarded', null, ['lines' => [['sku' => 'CUP', 'quantity' => 1, 'unit_cost' => 100]]] + $this->order());
    }

    public function testARefusedMessageIsNotRemembered(): void
    {
        try {
            $this->receive('reseller', Partners::resellerKey(), 'msg_1', 'order.forwarded', null, ['lines' => []] + $this->order());
        } catch (SyndicationRefused) {
        }

        self::assertSame([], $this->store->received);
        self::assertSame([], $this->store->entries);
    }

    /**
     * An id is the sender's own: another partner using the same one is a different message.
     */
    public function testTheSameIdFromTwoPartnersIsTwoMessages(): void
    {
        self::assertTrue($this->receive('supplier', Partners::supplierKey(), 'msg_1', $this->product(5), '2026-11-03T09:00:00.000000+00:00'));
        self::assertTrue($this->receive('reseller', Partners::resellerKey(), 'msg_1', 'order.forwarded', null, $this->order()));

        self::assertSame(['supplier msg_1', 'reseller msg_1'], array_keys($this->store->received));
        self::assertCount(1, $this->handler->handled);
    }

    /**
     * The supplier pays a line at a cost it published to that reseller, in effect within the grace window: today's,
     * or one replaced less than seven days ago — never what the reseller claims on its own.
     */
    public function testALineIsPaidOnlyAtACostPublishedToThatReseller(): void
    {
        $this->publish('TEA-1', 650, '-3 days');
        $this->publish('TEA-1', 600, '-1 day');

        self::assertTrue($this->receive('reseller', Partners::resellerKey(), 'msg_1', 'order.forwarded', null, ['order' => 'R-1', 'lines' => [['sku' => 'TEA-1', 'quantity' => 1, 'unit_cost' => 700]]] + $this->order()));
        self::assertTrue($this->receive('reseller', Partners::resellerKey(), 'msg_2', 'order.forwarded', null, ['order' => 'R-2', 'lines' => [['sku' => 'TEA-1', 'quantity' => 2, 'unit_cost' => 650]]] + $this->order()));
        self::assertTrue($this->receive('reseller', Partners::resellerKey(), 'msg_3', 'order.forwarded', null, ['order' => 'R-3', 'lines' => [['sku' => 'TEA-1', 'quantity' => 1, 'unit_cost' => 600]]] + $this->order()));
        self::assertSame([700, 1_300, 600], array_map(static fn (LedgerEntry $entry): int => $entry->amount->minorUnits, $this->store->entries));

        foreach ([['TEA-1', 0, 'CAD 0.00'], ['TEA-1', 1, 'CAD 0.01'], ['MUG', 700, 'CAD 7.00']] as $i => [$sku, $cost, $shown]) {
            try {
                $this->receive('reseller', Partners::resellerKey(), 'msg_x'.$i, 'order.forwarded', null, ['order' => 'R-9', 'lines' => [['sku' => $sku, 'quantity' => 1, 'unit_cost' => $cost]]] + $this->order());
                self::fail('A cost never published was taken.');
            } catch (SyndicationRefused $syndicationRefused) {
                self::assertStringContainsString(\sprintf('The reseller reseller owes %s for %s: no price published to it in the last 7 days costs that.', substr($shown, 4), $sku), $syndicationRefused->getMessage());
            }
        }

        self::assertCount(3, $this->store->entries);
        self::assertCount(3, $this->handler->handled);
    }

    public function testACostReplacedBeforeTheGraceWindowIsRefused(): void
    {
        $this->publish('TEA-1', 650, '-8 days');

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('no price published to it in the last 7 days costs that.');

        $this->receive('reseller', Partners::resellerKey(), 'msg_1', 'order.forwarded', null, $this->order());
    }

    public function testACostPublishedToAnotherResellerOrInAnotherCurrencyIsRefused(): void
    {
        $this->store->addPartner(Partners::reseller('other', ['TEA-1']));
        $this->store->recordPrice(PublishedPrice::of(Partners::reseller('other', ['TEA-1']), 'TEA-1', Money::of(1_000, Currency::of('CAD')), Money::of(500, Currency::of('CAD')), $this->at));
        $this->store->recordPrice(PublishedPrice::of(Partners::reseller('reseller', ['TEA-1']), 'TEA-1', Money::of(1_000, Currency::of('USD')), Money::of(400, Currency::of('USD')), $this->at->modify('-30 days')));

        foreach ([['CAD', 500], ['USD', 700]] as $i => [$currency, $cost]) {
            try {
                $this->receive('reseller', Partners::resellerKey(), 'msg_'.$i, 'order.forwarded', null, ['currency' => $currency, 'lines' => [['sku' => 'TEA-1', 'quantity' => 1, 'unit_cost' => $cost]]] + $this->order());
                self::fail('A cost never published to this reseller was taken.');
            } catch (SyndicationRefused) {
            }
        }

        self::assertSame([], $this->store->entries);
    }

    public function testTheGraceWindowIsZeroDaysOrMore(): void
    {
        self::assertInstanceOf(Inbox::class, new Inbox($this->store, $this->store, $this->store, $this->store, $this->store, 'shop', 300, 0));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A grace window is zero days or more; -1 is refused.');

        new Inbox($this->store, $this->store, $this->store, $this->store, $this->store, 'shop', 300, -1);
    }

    public function testThePartnerKeyIsItsOwn(): void
    {
        $key = new PartnerKey(Partners::supplier());

        $public = $key->keys()[0];
        self::assertInstanceOf(Ed25519PublicKey::class, $public);
        self::assertSame(Partners::supplierKey()->publicKey()->toString(), $public->toString());
        self::assertEquals($key->keys(), $key->refresh());
    }

    private function publish(string $sku, int $cost, string $when): void
    {
        $this->store->recordPrice(PublishedPrice::of(Partners::reseller('reseller', ['TEA-1', 'MUG']), $sku, Money::of(2_000, Currency::of('CAD')), Money::of($cost, Currency::of('CAD')), $this->at->modify($when)));
    }

    /**
     * @return array<string, mixed>
     */
    private function order(): array
    {
        return ['order' => 'R-77', 'currency' => 'CAD', 'lines' => [['sku' => 'TEA-1', 'quantity' => 2, 'unit_cost' => 700], ['sku' => 'MUG', 'quantity' => 1, 'unit_cost' => 1_100]], 'ship_to' => ['name' => 'Ada', 'x']];
    }

    private function product(int $available): string
    {
        $this->productAvailable = $available;

        return 'product.updated';
    }

    private int $productAvailable = 1;

    /**
     * @param array<string, mixed>|null $data
     */
    private function receive(string $partner, Ed25519SecretKey $key, string $id, string $type, ?string $timestamp, ?array $data = null, string $audience = 'shop', ?DateTimeImmutable $signedAt = null): bool
    {
        $data ??= ['sku' => 'TEA-1', 'name' => 'Green tea', 'currency' => 'CAD', 'retail' => 1_000, 'cost' => 700, 'tax_category' => 'food', 'available' => $this->productAvailable, 'active' => true];
        $envelope = ['type' => $type, 'audience' => $audience, 'data' => $data];

        if (null !== $timestamp) {
            $envelope['timestamp'] = $timestamp;
        }

        $body = json_encode($envelope, \JSON_THROW_ON_ERROR);
        $headers = new Signer()->sign(WebhookId::fromString($id), WebhookTimestamp::fromDateTime($signedAt ?? $this->at), $body, $key);

        return new Inbox($this->store, $this->store, $this->store, $this->store, $this->store, 'shop', 300, handlers: [$this->handler])->receive($partner, WebhookHeaders::fromStrings(...array_values($headers->toArray())), $body, $this->at);
    }
}
