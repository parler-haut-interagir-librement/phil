<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use Phil\CommerceLogisticsBundle\Domain\Model\SerialNumber;

/**
 * Finds and stores serialised units. `Infrastructure\Doctrine\DoctrineLogisticsRepository` is the Doctrine adapter.
 */
interface SerialNumberRepository
{
    public function findSerial(string $sku, string $serial): ?SerialNumber;

    public function addSerial(SerialNumber $serial): void;

    /**
     * How many units of the SKU are in stock at the location under a serial number.
     */
    public function serialsInStock(string $sku, string $location): int;

    /**
     * @return list<SerialNumber> the units that left with the order, by SKU then serial
     */
    public function serialsOfOrder(string $orderNumber): array;
}
