<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostEntryKind;
use Phil\CommerceLogisticsBundle\Domain\Cost\VarianceReason;
use Phil\CommerceLogisticsBundle\Domain\Delivery\DeliveryKind;
use Phil\CommerceLogisticsBundle\Domain\Document\ShippingDocumentKind;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\ConventionQuantity;
use Phil\CommerceLogisticsBundle\Domain\Model\CostEntry;
use Phil\CommerceLogisticsBundle\Domain\Model\CostLayer;
use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\PrintJob;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\SerialNumber;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingCost;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingDocument;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingWave;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Model\StockPolicy;
use Phil\CommerceLogisticsBundle\Domain\Model\WaveAssignment;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseState;
use Phil\CommerceLogisticsBundle\Domain\Serial\SerialState;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Domain\Stock\ReservationState;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the logistics tables.
 */
#[CoversClass(Location::class)]
#[CoversClass(StockLevel::class)]
#[CoversClass(StockMovement::class)]
#[CoversClass(Reservation::class)]
#[CoversClass(Shipment::class)]
#[CoversClass(ShippingCost::class)]
#[CoversClass(DeliveryMethod::class)]
#[CoversClass(StockPolicy::class)]
#[CoversClass(Convention::class)]
#[CoversClass(ConventionQuantity::class)]
#[CoversClass(PrintJob::class)]
#[CoversClass(PurchaseOrder::class)]
#[CoversClass(SerialNumber::class)]
#[CoversClass(ShippingDocument::class)]
#[CoversClass(ShippingWave::class)]
#[CoversClass(WaveAssignment::class)]
#[CoversClass(CostLayer::class)]
#[CoversClass(CostEntry::class)]
final class MappingTest extends TestCase
{
    public function testTheTables(): void
    {
        self::assertSame(['phil_commerce_logistics_location', ['code'], [
            'active' => ['active', 'boolean', null, false, null],
            'code' => ['code', 'string', 64, false, null],
            'name' => ['name', 'string', 255, false, null],
            'priority' => ['priority', 'integer', null, false, null],
        ]], $this->table(Location::class));

        self::assertSame(['phil_commerce_logistics_stock_level', ['sku', 'location'], [
            'onHand' => ['on_hand', 'integer', null, false, null],
            'reserved' => ['reserved', 'integer', null, false, null],
            'version' => ['version', 'integer', null, false, null],
            'sku' => ['sku', 'string', 64, false, null],
            'location' => ['location', 'string', 64, false, null],
        ]], $this->table(StockLevel::class));
        self::assertSame('version', $this->metadata(StockLevel::class)->versionField);

        self::assertSame(['phil_commerce_logistics_stock_movement', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'sku' => ['sku', 'string', 64, false, null],
            'location' => ['location', 'string', 64, false, null],
            'kind' => ['kind', 'string', 16, false, null],
            'onHandChange' => ['on_hand_change', 'integer', null, false, null],
            'reservedChange' => ['reserved_change', 'integer', null, false, null],
            'reference' => ['reference', 'string', 64, true, null],
            'movedAt' => ['moved_at', 'datetime_immutable', null, false, null],
            'reason' => ['reason', 'string', 16, true, null],
        ]], $this->table(StockMovement::class));
        self::assertSame(VarianceReason::class, $this->metadata(StockMovement::class)->fieldMappings['reason']->enumType);
        self::assertSame([['columns' => ['sku', 'location', 'moved_at']], ['columns' => ['reference']]], array_values($this->metadata(StockMovement::class)->table['indexes'] ?? []));
        self::assertSame(MovementKind::class, $this->metadata(StockMovement::class)->fieldMappings['kind']->enumType);

        self::assertSame(['phil_commerce_logistics_reservation', ['id'], [
            'state' => ['state', 'string', 16, false, null],
            'shipped' => ['shipped', 'integer', null, false, null],
            'returned' => ['returned', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'sku' => ['sku', 'string', 64, false, null],
            'location' => ['location', 'string', 64, false, null],
            'quantity' => ['quantity', 'integer', null, false, null],
        ]], $this->table(Reservation::class));
        self::assertSame([['columns' => ['order_number']]], array_values($this->metadata(Reservation::class)->table['indexes'] ?? []));
        self::assertSame(['default' => 0], $this->metadata(Reservation::class)->fieldMappings['returned']->options);
        self::assertSame(ReservationState::class, $this->metadata(Reservation::class)->fieldMappings['state']->enumType);

        self::assertSame(['phil_commerce_logistics_delivery_method', ['code'], [
            'active' => ['active', 'boolean', null, false, null],
            'code' => ['code', 'string', 64, false, null],
            'label' => ['label', 'string', 255, false, null],
            'kind' => ['kind', 'string', 16, false, null],
            'priceMinorUnits' => ['price', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'freeFromMinorUnits' => ['free_from', 'bigint', null, true, null],
            'zones' => ['zones', 'json', null, false, null],
            'minDays' => ['min_days', 'smallint', null, false, null],
            'maxDays' => ['max_days', 'smallint', null, false, null],
            'pickupLocation' => ['pickup_location', 'string', 64, true, null],
            'priority' => ['priority', 'integer', null, false, null],
        ]], $this->table(DeliveryMethod::class));
        self::assertSame(DeliveryKind::class, $this->metadata(DeliveryMethod::class)->fieldMappings['kind']->enumType);
    }

    /**
     * The longest codes and SKUs, and the ids, fit their columns.
     */
    public function testWhatTheyHoldFitsTheirColumns(): void
    {
        $location = Location::of(str_repeat('l', 64), str_repeat('n', 255));
        $level = StockLevel::of(str_repeat('S', 64), $location);
        $level->receive(1);

        $movement = StockMovement::of($level, MovementKind::Received, 1, 0, null, new DateTimeImmutable());
        $reservation = Reservation::hold(str_repeat('9', 64), $level, 1);
        $method = DeliveryMethod::of(str_repeat('d', 64), str_repeat('l', 255), DeliveryKind::Shipping, Money::zero(Currency::of('CAD')));

        self::assertSame($this->metadata(Location::class)->fieldMappings['code']->length, \strlen($location->code));
        self::assertSame($this->metadata(StockLevel::class)->fieldMappings['location']->length, \strlen($level->location));
        self::assertSame($this->metadata(StockLevel::class)->fieldMappings['sku']->length, \strlen($level->sku));
        self::assertSame($this->metadata(StockMovement::class)->fieldMappings['id']->length, \strlen($movement->id));
        self::assertSame($this->metadata(Reservation::class)->fieldMappings['id']->length, \strlen($reservation->id));
        self::assertSame($this->metadata(Reservation::class)->fieldMappings['orderNumber']->length, \strlen($reservation->orderNumber));
        self::assertSame($this->metadata(DeliveryMethod::class)->fieldMappings['code']->length, \strlen($method->code));
        self::assertSame($this->metadata(DeliveryMethod::class)->fieldMappings['label']->length, \strlen($method->label));
    }

    public function testTheShipments(): void
    {
        self::assertSame(['phil_commerce_logistics_shipment', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'lines' => ['line_items', 'json', null, false, null],
            'shippedAt' => ['shipped_at', 'datetime_immutable', null, false, null],
            'carrier' => ['carrier', 'string', 64, true, null],
            'tracking' => ['tracking', 'string', 128, true, null],
        ]], $this->table(Shipment::class));
        self::assertSame([['columns' => ['order_number']]], array_values($this->metadata(Shipment::class)->table['indexes'] ?? []));

        $shipment = Shipment::of(str_repeat('o', 64), ['product:A' => 1], new DateTimeImmutable(), str_repeat('c', 64), str_repeat('t', 128));
        self::assertSame($this->metadata(Shipment::class)->fieldMappings['id']->length, \strlen($shipment->id));
        self::assertSame($this->metadata(Shipment::class)->fieldMappings['orderNumber']->length, \strlen($shipment->orderNumber));
        self::assertSame($this->metadata(Shipment::class)->fieldMappings['carrier']->length, \strlen((string) $shipment->carrier));
        self::assertSame($this->metadata(Shipment::class)->fieldMappings['tracking']->length, \strlen((string) $shipment->tracking));
    }

    public function testTheCostsOfShipments(): void
    {
        self::assertSame(['phil_commerce_logistics_shipping_cost', ['shipment'], [
            'shipment' => ['shipment', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'carrier' => ['carrier', 'string', 64, false, null],
            'service' => ['service', 'string', 32, false, null],
            'tracking' => ['tracking', 'string', 128, true, null],
            'costMinorUnits' => ['cost', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'recordedAt' => ['recorded_at', 'datetime_immutable', null, false, null],
        ]], $this->table(ShippingCost::class));
        self::assertSame([['columns' => ['order_number']]], array_values($this->metadata(ShippingCost::class)->table['indexes'] ?? []));

        $cost = ShippingCost::of(Shipment::of(str_repeat('o', 64), ['product:A' => 1], new DateTimeImmutable()), str_repeat('c', 64), str_repeat('s', 32), Money::of(1, Currency::of('CAD')), new DateTimeImmutable(), str_repeat('t', 128));
        foreach (['shipment' => $cost->shipment, 'orderNumber' => $cost->orderNumber, 'carrier' => $cost->carrier, 'service' => $cost->service, 'tracking' => (string) $cost->tracking] as $field => $value) {
            self::assertSame($this->metadata(ShippingCost::class)->fieldMappings[$field]->length, \strlen($value));
        }
    }

    public function testTheTablesOfPoliciesConventionsAndPurchases(): void
    {
        self::assertSame(['phil_commerce_logistics_stock_policy', ['sku'], [
            'backorders' => ['backorders', 'boolean', null, false, null],
            'sku' => ['sku', 'string', 64, false, null],
        ]], $this->table(StockPolicy::class));

        self::assertSame(['phil_commerce_logistics_convention', ['code'], [
            'code' => ['code', 'string', 64, false, null],
            'name' => ['name', 'string', 255, false, null],
            'startsOn' => ['starts_on', 'date_immutable', null, false, null],
            'endsOn' => ['ends_on', 'date_immutable', null, false, null],
        ]], $this->table(Convention::class));
        self::assertSame([['columns' => ['ends_on']]], array_values($this->metadata(Convention::class)->table['indexes'] ?? []));

        self::assertSame(['phil_commerce_logistics_convention_quantity', ['convention', 'sku'], [
            'convention' => ['convention', 'string', 64, false, null],
            'sku' => ['sku', 'string', 64, false, null],
            'quantity' => ['quantity', 'integer', null, false, null],
        ]], $this->table(ConventionQuantity::class));
        self::assertSame([['columns' => ['sku']]], array_values($this->metadata(ConventionQuantity::class)->table['indexes'] ?? []));

        self::assertSame(['phil_commerce_logistics_print_job', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'sku' => ['sku', 'string', 64, false, null],
            'quantity' => ['quantity', 'integer', null, false, null],
            'sentAt' => ['sent_at', 'datetime_immutable', null, false, null],
        ]], $this->table(PrintJob::class));
        self::assertSame([['columns' => ['order_number', 'sku']]], array_values($this->metadata(PrintJob::class)->table['uniqueConstraints'] ?? []));

        self::assertSame(['phil_commerce_logistics_purchase_order', ['number'], [
            'state' => ['state', 'string', 16, false, null],
            'received' => ['received', 'json', null, false, null],
            'lineTerms' => ['line_terms', 'json', null, true, null],
            'number' => ['number', 'string', 64, false, null],
            'supplier' => ['supplier', 'string', 255, false, null],
            'location' => ['location', 'string', 64, false, null],
            'ordered' => ['ordered', 'json', null, false, null],
            'orderedAt' => ['ordered_at', 'datetime_immutable', null, false, null],
            'expectedOn' => ['expected_on', 'date_immutable', null, true, null],
        ]], $this->table(PurchaseOrder::class));
        self::assertSame([['columns' => ['state']]], array_values($this->metadata(PurchaseOrder::class)->table['indexes'] ?? []));
        self::assertSame(PurchaseState::class, $this->metadata(PurchaseOrder::class)->fieldMappings['state']->enumType);

        $convention = Convention::of(str_repeat('c', 64), str_repeat('n', 255), new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
        $quantity = ConventionQuantity::of($convention, str_repeat('S', 64), 1);
        $job = PrintJob::of(str_repeat('o', 64), str_repeat('S', 64), 1, new DateTimeImmutable());
        $purchase = PurchaseOrder::of(str_repeat('p', 64), str_repeat('s', 255), Location::of(str_repeat('l', 64), 'L'), ['A' => 1], new DateTimeImmutable());

        foreach ([[Convention::class, 'code', $convention->code], [Convention::class, 'name', $convention->name], [ConventionQuantity::class, 'sku', $quantity->sku], [PrintJob::class, 'id', $job->id], [PrintJob::class, 'orderNumber', $job->orderNumber], [PurchaseOrder::class, 'number', $purchase->number], [PurchaseOrder::class, 'supplier', $purchase->supplier], [PurchaseOrder::class, 'location', $purchase->location]] as [$class, $field, $value]) {
            self::assertSame($this->metadata($class)->fieldMappings[$field]->length, mb_strlen($value), $class.'::'.$field);
        }
    }

    public function testTheTablesOfSerialsDocumentsAndWaves(): void
    {
        self::assertSame(['phil_commerce_logistics_serial_number', ['sku', 'serial'], [
            'state' => ['state', 'string', 16, false, null],
            'orderNumber' => ['order_number', 'string', 64, true, null],
            'shippedAt' => ['shipped_at', 'datetime_immutable', null, true, null],
            'sku' => ['sku', 'string', 64, false, null],
            'serial' => ['serial', 'string', 128, false, null],
            'location' => ['location', 'string', 64, false, null],
            'receivedAt' => ['received_at', 'datetime_immutable', null, false, null],
        ]], $this->table(SerialNumber::class));
        self::assertSame([['columns' => ['sku', 'location', 'state']], ['columns' => ['order_number']]], array_values($this->metadata(SerialNumber::class)->table['indexes'] ?? []));
        self::assertSame(SerialState::class, $this->metadata(SerialNumber::class)->fieldMappings['state']->enumType);

        self::assertSame(['phil_commerce_logistics_document', ['number'], [
            'number' => ['number', 'string', 32, false, null],
            'kind' => ['kind', 'string', 16, false, null],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'shipmentId' => ['shipment', 'string', 36, false, null],
            'issuedAt' => ['issued_at', 'datetime_immutable', null, false, null],
        ]], $this->table(ShippingDocument::class));
        self::assertSame([['columns' => ['order_number']]], array_values($this->metadata(ShippingDocument::class)->table['indexes'] ?? []));
        self::assertSame([['columns' => ['kind', 'order_number', 'shipment']]], array_values($this->metadata(ShippingDocument::class)->table['uniqueConstraints'] ?? []));
        self::assertSame(ShippingDocumentKind::class, $this->metadata(ShippingDocument::class)->fieldMappings['kind']->enumType);

        self::assertSame(['phil_commerce_logistics_wave', ['code'], [
            'releasedAt' => ['released_at', 'datetime_immutable', null, true, null],
            'code' => ['code', 'string', 64, false, null],
            'name' => ['name', 'string', 255, false, null],
            'estimatedOn' => ['estimated_on', 'date_immutable', null, false, null],
        ]], $this->table(ShippingWave::class));
        self::assertSame([['columns' => ['released_at', 'estimated_on']]], array_values($this->metadata(ShippingWave::class)->table['indexes'] ?? []));

        self::assertSame(['phil_commerce_logistics_wave_order', ['orderNumber'], [
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'wave' => ['wave', 'string', 64, false, null],
        ]], $this->table(WaveAssignment::class));
        self::assertSame([['columns' => ['wave']]], array_values($this->metadata(WaveAssignment::class)->table['indexes'] ?? []));

        $level = StockLevel::of(str_repeat('S', 64), Location::of(str_repeat('l', 64), 'L'));
        $serial = SerialNumber::of($level, str_repeat('x', 128), new DateTimeImmutable());
        $shipment = Shipment::of(str_repeat('o', 64), ['product:A' => 1], new DateTimeImmutable());
        $document = ShippingDocument::deliveryNote(str_repeat('9', 32), $shipment, new DateTimeImmutable());
        $wave = ShippingWave::plan(str_repeat('w', 64), str_repeat('n', 255), new DateTimeImmutable());

        foreach ([[SerialNumber::class, 'serial', $serial->serial], [SerialNumber::class, 'location', $serial->location()], [ShippingDocument::class, 'number', $document->number], [ShippingDocument::class, 'shipmentId', (string) $document->shipment()], [ShippingDocument::class, 'kind', ShippingDocumentKind::DeliveryNote->value], [ShippingWave::class, 'code', $wave->code], [ShippingWave::class, 'name', $wave->name]] as [$class, $field, $value]) {
            self::assertGreaterThanOrEqual(mb_strlen($value), $this->metadata($class)->fieldMappings[$field]->length, $class.'::'.$field);
        }
    }

    public function testThePolicyAndTheWaveAssignmentKeepTheLongestSkuAndCodeExactly(): void
    {
        $policy = StockPolicy::of(str_repeat('s', 64));
        $assignment = WaveAssignment::of(str_repeat('o', 64), ShippingWave::plan(str_repeat('w', 64), 'A run', new DateTimeImmutable()));

        foreach ([[StockPolicy::class, 'sku', $policy->sku], [WaveAssignment::class, 'orderNumber', $assignment->orderNumber], [WaveAssignment::class, 'wave', $assignment->wave()]] as [$class, $field, $value]) {
            self::assertSame(\strlen($value), $this->metadata($class)->fieldMappings[$field]->length, $class.'::'.$field);
        }
    }

    public function testTheCostTables(): void
    {
        self::assertSame(['phil_commerce_logistics_cost_layer', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'sku' => ['sku', 'string', 64, false, null],
            'location' => ['location', 'string', 64, false, null],
            'owner' => ['owner', 'string', 64, true, null],
            'units' => ['units', 'integer', null, false, null],
            'value' => ['value', 'bigint', null, false, null],
            'currency' => ['currency', 'string', 3, false, true],
            'receivedAt' => ['received_at', 'datetime_immutable', null, false, null],
            'reference' => ['reference', 'string', 64, true, null],
        ]], $this->table(CostLayer::class));
        self::assertSame([['columns' => ['sku', 'location']]], array_values($this->metadata(CostLayer::class)->table['indexes'] ?? []));

        self::assertSame(['phil_commerce_logistics_cost_entry', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'sku' => ['sku', 'string', 64, false, null],
            'location' => ['location', 'string', 64, false, null],
            'kind' => ['kind', 'string', 16, false, null],
            'owner' => ['owner', 'string', 64, true, null],
            'units' => ['units', 'integer', null, false, null],
            'uncosted' => ['uncosted', 'integer', null, false, null],
            'value' => ['value', 'bigint', null, false, null],
            'currency' => ['currency', 'string', 3, false, true],
            'reason' => ['reason', 'string', 16, true, null],
            'reference' => ['reference', 'string', 64, true, null],
            'movedAt' => ['moved_at', 'datetime_immutable', null, false, null],
        ]], $this->table(CostEntry::class));
        self::assertSame([['columns' => ['sku', 'location', 'moved_at']], ['columns' => ['reference']], ['columns' => ['moved_at']]], array_values($this->metadata(CostEntry::class)->table['indexes'] ?? []));
        self::assertSame([CostEntryKind::class, VarianceReason::class], [$this->metadata(CostEntry::class)->fieldMappings['kind']->enumType, $this->metadata(CostEntry::class)->fieldMappings['reason']->enumType]);

        // Every value of the enumerations fits its column.
        foreach ([...CostEntryKind::cases(), ...VarianceReason::cases()] as $case) {
            self::assertLessThanOrEqual(16, \strlen($case->value), $case->name);
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
