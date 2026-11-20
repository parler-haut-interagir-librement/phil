<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;

/**
 * Finds and stores stock levels, the stock journal and reservations. `Infrastructure\Doctrine\DoctrineLogisticsRepository`
 * is the Doctrine adapter.
 */
interface StockRepository
{
    public function level(string $sku, string $location): ?StockLevel;

    /**
     * @return list<StockLevel> every level of the SKU, at every location, in no particular order
     */
    public function levelsOf(string $sku): array;

    /**
     * @param list<string> $skus
     *
     * @return list<StockLevel> every level of these SKUs, at every location, in no particular order
     */
    public function levelsOfSkus(array $skus): array;

    public function addLevel(StockLevel $level): void;

    public function record(StockMovement $movement): void;

    /**
     * @return list<StockMovement> the journal of a SKU at a location, oldest first
     */
    public function journal(string $sku, string $location): array;

    /**
     * @return list<Reservation> every reservation of the order, oldest first
     */
    public function reservationsOf(string $orderNumber): array;

    public function addReservation(Reservation $reservation): void;
}
