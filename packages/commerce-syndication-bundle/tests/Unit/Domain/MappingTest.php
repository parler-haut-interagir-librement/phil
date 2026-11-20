<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceSyndicationBundle\Domain\Model\FedSku;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Domain\Model\ReceivedMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\SyndicatedProduct;
use Phil\CommerceSyndicationBundle\Domain\Tracking\ReturnState;
use Phil\CommerceSyndicationBundle\Domain\ValueObject\FeedItem;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the syndication.
 */
#[CoversClass(Partner::class)]
#[CoversClass(OutgoingMessage::class)]
#[CoversClass(ReceivedMessage::class)]
#[CoversClass(SyndicatedProduct::class)]
#[CoversClass(LedgerEntry::class)]
#[CoversClass(PublishedPrice::class)]
#[CoversClass(FedSku::class)]
#[CoversClass(PartnerShipment::class)]
#[CoversClass(PartnerReturn::class)]
final class MappingTest extends TestCase
{
    public function testThePartners(): void
    {
        self::assertSame(['phil_commerce_syndication_partner', ['code'], [
            'active' => ['active', 'boolean', null, false, null],
            'skus' => ['skus', 'json', null, false, null],
            'share' => ['share', 'integer', null, false, null],
            'code' => ['code', 'string', 63, false, null],
            'name' => ['name', 'string', 255, false, null],
            'role' => ['role', 'string', 16, false, null],
            'endpoint' => ['endpoint', 'string', 2048, false, null],
            'publicKey' => ['public_key', 'string', 64, false, null],
        ]], $this->table(Partner::class));

        $partner = Partner::of(str_repeat('p', 63), str_repeat('n', 255), Partners::supplier()->role, 'https://'.str_repeat('a', 2040), Partners::supplierKey()->publicKey()->toString());
        self::assertSame($this->metadata(Partner::class)->fieldMappings['code']->length, \strlen($partner->code));
        self::assertSame($this->metadata(Partner::class)->fieldMappings['name']->length, \strlen($partner->name));
        self::assertSame($this->metadata(Partner::class)->fieldMappings['endpoint']->length, \strlen($partner->endpoint()));
        self::assertGreaterThanOrEqual(\strlen($partner->publicKey()), $this->metadata(Partner::class)->fieldMappings['publicKey']->length);
    }

    public function testTheMessages(): void
    {
        self::assertSame(['phil_commerce_syndication_outgoing', ['id'], [
            'attempts' => ['attempts', 'smallint', null, false, null],
            'nextAttemptAt' => ['next_attempt_at', 'datetime_utc_immutable', null, true, null],
            'deliveredAt' => ['delivered_at', 'datetime_utc_immutable', null, true, null],
            'lastError' => ['last_error', 'string', 255, true, null],
            'givenUpAt' => ['given_up_at', 'datetime_utc_immutable', null, true, null],
            'claimedUntil' => ['claimed_until', 'datetime_utc_immutable', null, true, null],
            'id' => ['id', 'string', 36, false, null],
            'partner' => ['partner', 'string', 63, false, null],
            'type' => ['type', 'string', 64, false, null],
            'payload' => ['data', 'json', null, false, null],
            'createdAt' => ['created_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(OutgoingMessage::class));
        self::assertSame([['columns' => ['delivered_at', 'next_attempt_at']], ['columns' => ['given_up_at']]], array_values($this->metadata(OutgoingMessage::class)->table['indexes'] ?? []));

        self::assertSame(['phil_commerce_syndication_received', ['partner', 'id'], [
            'partner' => ['partner', 'string', 63, false, null],
            'id' => ['id', 'string', 255, false, null],
            'type' => ['type', 'string', 64, false, null],
            'receivedAt' => ['received_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(ReceivedMessage::class));
        self::assertSame([['columns' => ['received_at']]], array_values($this->metadata(ReceivedMessage::class)->table['indexes'] ?? []));

        $received = ReceivedMessage::of(str_repeat('i', 255), Partners::supplier(str_repeat('p', 63)), 'a.'.str_repeat('b', 62), new DateTimeImmutable());
        self::assertSame($this->metadata(ReceivedMessage::class)->fieldMappings['id']->length, \strlen($received->id));
        self::assertSame($this->metadata(ReceivedMessage::class)->fieldMappings['partner']->length, \strlen($received->partner));
        self::assertSame($this->metadata(ReceivedMessage::class)->fieldMappings['type']->length, \strlen($received->type));

        $message = OutgoingMessage::to(Partners::reseller(), 'a.'.str_repeat('b', 62), [], new DateTimeImmutable());
        self::assertGreaterThanOrEqual(\strlen($message->id), $this->metadata(OutgoingMessage::class)->fieldMappings['id']->length);
        self::assertSame($this->metadata(OutgoingMessage::class)->fieldMappings['type']->length, \strlen($message->type));
    }

    public function testThePublishedPrices(): void
    {
        self::assertSame(['phil_commerce_syndication_published_price', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'reseller' => ['reseller', 'string', 63, false, null],
            'sku' => ['sku', 'string', 64, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'retailMinorUnits' => ['retail', 'bigint', null, false, null],
            'costMinorUnits' => ['cost', 'bigint', null, false, null],
            'publishedAt' => ['published_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(PublishedPrice::class));
        self::assertSame([['columns' => ['reseller', 'sku', 'currency', 'published_at']]], array_values($this->metadata(PublishedPrice::class)->table['indexes'] ?? []));

        $price = PublishedPrice::of(Partners::reseller(str_repeat('r', 63)), str_repeat('s', 64), Money::of(1_000, Currency::of('CAD')), Money::of(700, Currency::of('CAD')), new DateTimeImmutable());
        self::assertSame($this->metadata(PublishedPrice::class)->fieldMappings['id']->length, \strlen($price->id));
        self::assertSame($this->metadata(PublishedPrice::class)->fieldMappings['reseller']->length, \strlen($price->reseller));
        self::assertSame($this->metadata(PublishedPrice::class)->fieldMappings['sku']->length, \strlen($price->sku));
        self::assertTrue($price->samePrice(Money::of(1_000, Currency::of('CAD')), Money::of(700, Currency::of('CAD'))));
        self::assertFalse($price->samePrice(Money::of(1_000, Currency::of('USD')), Money::of(700, Currency::of('USD'))));
        self::assertFalse($price->samePrice(Money::of(1_001, Currency::of('CAD')), Money::of(700, Currency::of('CAD'))));
        self::assertFalse($price->samePrice(Money::of(1_000, Currency::of('CAD')), Money::of(701, Currency::of('CAD'))));
    }

    public function testTheFedSkus(): void
    {
        self::assertSame(['phil_commerce_syndication_fed_sku', ['partner', 'sku'], [
            'partner' => ['partner', 'string', 63, false, null],
            'sku' => ['sku', 'string', 64, false, null],
        ]], $this->table(FedSku::class));
        self::assertSame([['columns' => ['sku']]], array_values($this->metadata(FedSku::class)->table['indexes'] ?? []));

        $fed = FedSku::of(Partners::reseller(str_repeat('r', 63)), str_repeat('s', 64));
        self::assertSame([63, 64], [\strlen($fed->partner), \strlen($fed->sku)]);
        self::assertSame($this->metadata(FedSku::class)->fieldMappings['partner']->length, $this->metadata(Partner::class)->fieldMappings['code']->length);
    }

    public function testTheProductsAndTheLedger(): void
    {
        self::assertSame(['phil_commerce_syndication_product', ['supplier', 'sku'], [
            'name' => ['name', 'string', 255, false, null],
            'retailMinorUnits' => ['retail', 'bigint', null, false, null],
            'costMinorUnits' => ['cost', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'taxCategory' => ['tax_category', 'string', 64, false, null],
            'available' => ['available', 'integer', null, false, null],
            'active' => ['active', 'boolean', null, false, null],
            'supplier' => ['supplier', 'string', 63, false, null],
            'sku' => ['sku', 'string', 64, false, null],
            'describedAt' => ['described_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(SyndicatedProduct::class));

        self::assertSame(['phil_commerce_syndication_ledger', ['id'], [
            'settledAt' => ['settled_at', 'datetime_utc_immutable', null, true, null],
            'id' => ['id', 'string', 36, false, true],
            'partner' => ['partner', 'string', 63, false, null],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'amountMinorUnits' => ['amount', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'recordedAt' => ['recorded_at', 'datetime_utc_immutable', null, false, null],
            'returnKey' => ['return_number', 'string', 64, false, null],
            'lines' => ['line_items', 'json', null, false, null],
        ]], $this->table(LedgerEntry::class));
        self::assertSame([['columns' => ['partner', 'order_number', 'return_number']]], array_values($this->metadata(LedgerEntry::class)->table['uniqueConstraints'] ?? []));

        $product = SyndicatedProduct::from(Partners::supplier(str_repeat('s', 63)), FeedItem::of(str_repeat('A', 64), str_repeat('n', 255), Money::of(1, Currency::of('CAD')), Money::of(1, Currency::of('CAD')), str_repeat('t', 64), 1), new DateTimeImmutable());
        self::assertSame($this->metadata(SyndicatedProduct::class)->fieldMappings['sku']->length, \strlen($product->sku));
        self::assertSame($this->metadata(SyndicatedProduct::class)->fieldMappings['name']->length, \strlen($product->name()));
        self::assertSame($this->metadata(SyndicatedProduct::class)->fieldMappings['taxCategory']->length, \strlen($product->taxCategory()));
        self::assertSame(36, \strlen(LedgerEntry::of(Partners::supplier(), 'A-1', Money::of(1, Currency::of('CAD')), new DateTimeImmutable())->id));
    }

    public function testTheShipmentsAndReturnsBetweenPartners(): void
    {
        self::assertSame(['phil_commerce_syndication_shipment', ['partner', 'shipment'], [
            'partner' => ['partner', 'string', 63, false, null],
            'shipment' => ['shipment', 'string', 64, false, null],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'lines' => ['line_items', 'json', null, false, null],
            'carrier' => ['carrier', 'string', 64, true, null],
            'tracking' => ['tracking', 'string', 128, true, null],
            'shippedAt' => ['shipped_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(PartnerShipment::class));
        self::assertSame([['columns' => ['order_number']]], array_values($this->metadata(PartnerShipment::class)->table['indexes'] ?? []));

        self::assertSame(['phil_commerce_syndication_return', ['partner', 'returnNumber'], [
            'state' => ['state', 'string', 16, false, null],
            'received' => ['received', 'json', null, false, null],
            'creditMinorUnits' => ['credit', 'bigint', null, true, null],
            'receivedAt' => ['received_at', 'datetime_utc_immutable', null, true, null],
            'partner' => ['partner', 'string', 63, false, null],
            'returnNumber' => ['return_number', 'string', 64, false, null],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'requested' => ['requested', 'json', null, false, null],
            'carrier' => ['carrier', 'string', 64, true, null],
            'tracking' => ['tracking', 'string', 128, true, null],
            'requestedAt' => ['requested_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(PartnerReturn::class));
        self::assertSame([['columns' => ['partner', 'order_number']]], array_values($this->metadata(PartnerReturn::class)->table['indexes'] ?? []));
        self::assertSame(ReturnState::class, $this->metadata(PartnerReturn::class)->fieldMappings['state']->enumType);

        $partner = Partners::supplier(str_repeat('s', 63));
        $shipment = PartnerShipment::of($partner, str_repeat('h', 64), str_repeat('o', 64), ['A' => 1], new DateTimeImmutable(), str_repeat('c', 64), str_repeat('t', 128));
        $return = PartnerReturn::request($partner, str_repeat('r', 64), str_repeat('o', 64), Currency::of('CAD'), ['A' => 1], new DateTimeImmutable(), str_repeat('c', 64), str_repeat('t', 128));

        foreach ([[PartnerShipment::class, 'partner', $shipment->partner], [PartnerShipment::class, 'shipment', $shipment->shipment], [PartnerShipment::class, 'orderNumber', $shipment->orderNumber], [PartnerShipment::class, 'carrier', (string) $shipment->carrier], [PartnerShipment::class, 'tracking', (string) $shipment->tracking], [PartnerReturn::class, 'returnNumber', $return->returnNumber], [PartnerReturn::class, 'orderNumber', $return->orderNumber], [PartnerReturn::class, 'carrier', (string) $return->carrier], [PartnerReturn::class, 'tracking', (string) $return->tracking]] as [$class, $field, $value]) {
            self::assertSame($this->metadata($class)->fieldMappings[$field]->length, \strlen($value), $class.'::'.$field);
        }
    }

    /**
     * @param class-string $class
     *
     * @return array{string, array<int, string>, array<string, array{string, string, ?int, ?bool, mixed}>}
     */
    private function table(string $class): array
    {
        $metadata = $this->metadata($class);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        return [$metadata->getTableName(), $metadata->getIdentifierFieldNames(), $columns];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
