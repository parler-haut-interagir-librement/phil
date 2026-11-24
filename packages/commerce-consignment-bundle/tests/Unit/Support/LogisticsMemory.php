<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\PurchaseOrderRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;

/**
 * The repositories of the logistics module in memory, for its real services in the tests of the adapters.
 */
final class LogisticsMemory implements LocationRepository, StockRepository, PurchaseOrderRepository
{
    /** @var array<string, Location> */
    private array $locations = [];

    /** @var array<string, StockLevel> */
    private array $levels = [];

    /** @var list<StockMovement> */
    public array $movements = [];

    /** @var list<Reservation> */
    private array $reservations = [];

    /** @var array<string, PurchaseOrder> */
    private array $purchaseOrders = [];

    #[Override]
    public function findLocation(string $code): ?Location
    {
        return $this->locations[$code] ?? null;
    }

    #[Override]
    public function openLocations(): array
    {
        return array_values(array_filter($this->locations, static fn (Location $location): bool => $location->isActive()));
    }

    #[Override]
    public function addLocation(Location $location): void
    {
        $this->locations[$location->code] = $location;
    }

    #[Override]
    public function level(string $sku, string $location): ?StockLevel
    {
        return $this->levels[$sku."\0".$location] ?? null;
    }

    #[Override]
    public function levelsOf(string $sku): array
    {
        return array_values(array_filter($this->levels, static fn (StockLevel $level): bool => $level->sku === $sku));
    }

    #[Override]
    public function levelsOfSkus(array $skus): array
    {
        return array_values(array_filter($this->levels, static fn (StockLevel $level): bool => \in_array($level->sku, $skus, true)));
    }

    #[Override]
    public function addLevel(StockLevel $level): void
    {
        $this->levels[$level->sku."\0".$level->location] = $level;
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
        return array_values($this->purchaseOrders);
    }
}
