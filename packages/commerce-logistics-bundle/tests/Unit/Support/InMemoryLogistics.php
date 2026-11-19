<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\CommerceLogisticsBundle\Domain\Document\ShippingDocumentKind;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\ConventionQuantity;
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
use Phil\CommerceLogisticsBundle\Domain\Repository\ConventionRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\DeliveryMethodRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\PurchaseOrderRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\SerialNumberRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShipmentRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingCostRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingDocumentRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingWaveRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockPolicyRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceLogisticsBundle\Domain\Serial\SerialState;

final class InMemoryLogistics implements LocationRepository, StockRepository, StockPolicyRepository, DeliveryMethodRepository, ShipmentRepository, ShippingCostRepository, ConventionRepository, PurchaseOrderRepository, SerialNumberRepository, ShippingDocumentRepository, ShippingWaveRepository
{
    /** @var array<string, Location> */
    private array $locations = [];

    /** @var array<string, StockLevel> */
    public array $levels = [];

    /** @var list<StockMovement> */
    public array $movements = [];

    /** @var list<Reservation> */
    public array $reservations = [];

    /** @var list<Shipment> */
    public array $shipments = [];

    /** @var array<string, ShippingCost> */
    public array $costs = [];

    /** @var array<string, DeliveryMethod> */
    private array $methods = [];

    #[Override]
    public function findLocation(string $code): ?Location
    {
        return $this->locations[$code] ?? null;
    }

    #[Override]
    public function openLocations(): array
    {
        $open = array_values(array_filter($this->locations, static fn (Location $location): bool => $location->isActive()));
        usort($open, static fn (Location $left, Location $right): int => [$right->priority, $left->code] <=> [$left->priority, $right->code]);

        return $open;
    }

    #[Override]
    public function addLocation(Location $location): void
    {
        $this->locations[$location->code] = $location;
    }

    #[Override]
    public function level(string $sku, string $location): ?StockLevel
    {
        return $this->levels[$sku.'@'.$location] ?? null;
    }

    #[Override]
    public function levelsOf(string $sku): array
    {
        return array_values(array_filter($this->levels, static fn (StockLevel $level): bool => $level->sku === $sku));
    }

    /** @var list<list<string>> the SKU lists asked of levelsOfSkus(), one per lookup */
    public array $levelLookups = [];

    #[Override]
    public function levelsOfSkus(array $skus): array
    {
        $this->levelLookups[] = $skus;

        return array_values(array_filter($this->levels, static fn (StockLevel $level): bool => \in_array($level->sku, $skus, true)));
    }

    #[Override]
    public function addLevel(StockLevel $level): void
    {
        $this->levels[$level->sku.'@'.$level->location] = $level;
    }

    #[Override]
    public function record(StockMovement $movement): void
    {
        $this->movements[] = $movement;
    }

    #[Override]
    public function journal(string $sku, string $location): array
    {
        return array_values(array_filter($this->movements, static fn (StockMovement $movement): bool => $movement->sku === $sku && $movement->location === $location));
    }

    #[Override]
    public function reservationsOf(string $orderNumber): array
    {
        return array_values(array_filter($this->reservations, static fn (Reservation $reservation): bool => $reservation->orderNumber === $orderNumber));
    }

    #[Override]
    public function addReservation(Reservation $reservation): void
    {
        $this->reservations[] = $reservation;
    }

    #[Override]
    public function findMethod(string $code): ?DeliveryMethod
    {
        return $this->methods[$code] ?? null;
    }

    #[Override]
    public function activeMethods(): array
    {
        $active = array_values(array_filter($this->methods, static fn (DeliveryMethod $method): bool => $method->isActive()));
        usort($active, static fn (DeliveryMethod $left, DeliveryMethod $right): int => [$right->priority, $left->code] <=> [$left->priority, $right->code]);

        return $active;
    }

    #[Override]
    public function addMethod(DeliveryMethod $method): void
    {
        $this->methods[$method->code] = $method;
    }

    #[Override]
    public function addShipment(Shipment $shipment): void
    {
        $this->shipments[] = $shipment;
    }

    #[Override]
    public function shipmentsOf(string $orderNumber): array
    {
        return array_values(array_filter($this->shipments, static fn (Shipment $shipment): bool => $shipment->orderNumber === $orderNumber));
    }

    #[Override]
    public function addCost(ShippingCost $cost): void
    {
        $this->costs[$cost->shipment] = $cost;
    }

    #[Override]
    public function costOf(string $shipment): ?ShippingCost
    {
        return $this->costs[$shipment] ?? null;
    }

    #[Override]
    public function costsOf(string $orderNumber): array
    {
        return array_values(array_filter($this->costs, static fn (ShippingCost $cost): bool => $cost->orderNumber === $orderNumber));
    }

    /** @var array<string, StockPolicy> */
    public array $policies = [];

    /** @var array<string, Convention> */
    public array $conventions = [];

    /** @var array<string, ConventionQuantity> */
    public array $quantities = [];

    /** @var list<PrintJob> */
    public array $printJobs = [];

    /** @var array<string, PurchaseOrder> */
    public array $purchaseOrders = [];

    /** @var array<string, SerialNumber> */
    public array $serials = [];

    /** @var list<ShippingDocument> */
    public array $documents = [];

    /** @var array<string, ShippingWave> */
    public array $waves = [];

    /** @var array<string, WaveAssignment> */
    public array $assignments = [];

    #[Override]
    public function policy(string $sku): ?StockPolicy
    {
        return $this->policies[$sku] ?? null;
    }

    #[Override]
    public function policiesOf(array $skus): array
    {
        return array_values(array_filter($this->policies, static fn (StockPolicy $policy): bool => \in_array($policy->sku, $skus, true)));
    }

    #[Override]
    public function addPolicy(StockPolicy $policy): void
    {
        $this->policies[$policy->sku] = $policy;
    }

    #[Override]
    public function findConvention(string $code): ?Convention
    {
        return $this->conventions[$code] ?? null;
    }

    #[Override]
    public function addConvention(Convention $convention): void
    {
        $this->conventions[$convention->code] = $convention;
    }

    /** @var int how many times the conventions near a day were asked for */
    public int $meetingLookups = 0;

    /** @var int how many times the convention items among some SKUs were asked for */
    public int $conventionLookups = 0;

    #[Override]
    public function conventionsMeeting(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        ++$this->meetingLookups;

        $meeting = array_values(array_filter($this->conventions, static fn (Convention $convention): bool => $convention->meets($from, $until)));
        usort($meeting, static fn (Convention $left, Convention $right): int => [$left->startsOn(), $left->code] <=> [$right->startsOn(), $right->code]);

        return $meeting;
    }

    #[Override]
    public function quantity(string $convention, string $sku): ?ConventionQuantity
    {
        return $this->quantities[$convention.'@'.$sku] ?? null;
    }

    #[Override]
    public function quantitiesOf(string $convention): array
    {
        $quantities = array_values(array_filter($this->quantities, static fn (ConventionQuantity $quantity): bool => $quantity->convention === $convention));
        usort($quantities, static fn (ConventionQuantity $left, ConventionQuantity $right): int => $left->sku <=> $right->sku);

        return $quantities;
    }

    #[Override]
    public function addQuantity(ConventionQuantity $quantity): void
    {
        $this->quantities[$quantity->convention.'@'.$quantity->sku] = $quantity;
    }

    #[Override]
    public function conventionSkus(array $skus): array
    {
        ++$this->conventionLookups;

        $found = array_values(array_unique(array_map(static fn (ConventionQuantity $quantity): string => $quantity->sku, array_filter($this->quantities, static fn (ConventionQuantity $quantity): bool => \in_array($quantity->sku, $skus, true)))));
        sort($found);

        return $found;
    }

    #[Override]
    public function printJobsOf(string $orderNumber): array
    {
        return array_values(array_filter($this->printJobs, static fn (PrintJob $job): bool => $job->orderNumber === $orderNumber));
    }

    #[Override]
    public function addPrintJob(PrintJob $job): void
    {
        $this->printJobs[] = $job;
    }

    #[Override]
    public function findPurchaseOrder(string $number): ?PurchaseOrder
    {
        return $this->purchaseOrders[$number] ?? null;
    }

    #[Override]
    public function addPurchaseOrder(PurchaseOrder $order): void
    {
        $this->purchaseOrders[$order->number] = $order;
    }

    #[Override]
    public function openPurchaseOrders(): array
    {
        return array_values(array_filter($this->purchaseOrders, static fn (PurchaseOrder $order): bool => PurchaseState::Open === $order->state()));
    }

    #[Override]
    public function findSerial(string $sku, string $serial): ?SerialNumber
    {
        return $this->serials[$sku.'@'.$serial] ?? null;
    }

    #[Override]
    public function addSerial(SerialNumber $serial): void
    {
        $this->serials[$serial->sku.'@'.$serial->serial] = $serial;
    }

    #[Override]
    public function serialsInStock(string $sku, string $location): int
    {
        return \count(array_filter($this->serials, static fn (SerialNumber $serial): bool => $serial->sku === $sku && $serial->location() === $location && SerialState::InStock === $serial->state()));
    }

    #[Override]
    public function serialsOfOrder(string $orderNumber): array
    {
        $serials = array_values(array_filter($this->serials, static fn (SerialNumber $serial): bool => $serial->orderNumber() === $orderNumber));
        usort($serials, static fn (SerialNumber $left, SerialNumber $right): int => [$left->sku, $left->serial] <=> [$right->sku, $right->serial]);

        return $serials;
    }

    #[Override]
    public function findDocument(ShippingDocumentKind $kind, string $orderNumber, ?string $shipment): ?ShippingDocument
    {
        foreach ($this->documents as $document) {
            if ($kind === $document->kind && $orderNumber === $document->orderNumber && $shipment === $document->shipment()) {
                return $document;
            }
        }

        return null;
    }

    #[Override]
    public function addDocument(ShippingDocument $document): void
    {
        $this->documents[] = $document;
    }

    #[Override]
    public function findWave(string $code): ?ShippingWave
    {
        return $this->waves[$code] ?? null;
    }

    #[Override]
    public function addWave(ShippingWave $wave): void
    {
        $this->waves[$wave->code] = $wave;
    }

    #[Override]
    public function plannedWaves(): array
    {
        $planned = array_values(array_filter($this->waves, static fn (ShippingWave $wave): bool => !$wave->isReleased()));
        usort($planned, static fn (ShippingWave $left, ShippingWave $right): int => [$left->estimatedOn(), $left->code] <=> [$right->estimatedOn(), $right->code]);

        return $planned;
    }

    #[Override]
    public function assignmentOf(string $orderNumber): ?WaveAssignment
    {
        return $this->assignments[$orderNumber] ?? null;
    }

    #[Override]
    public function addAssignment(WaveAssignment $assignment): void
    {
        $this->assignments[$assignment->orderNumber] = $assignment;
    }

    #[Override]
    public function removeAssignment(WaveAssignment $assignment): void
    {
        unset($this->assignments[$assignment->orderNumber]);
    }

    #[Override]
    public function ordersOfWave(string $code): array
    {
        $orders = array_keys(array_filter($this->assignments, static fn (WaveAssignment $assignment): bool => $assignment->wave() === $code));
        $orders = array_map(strval(...), $orders);
        sort($orders);

        return $orders;
    }
}
