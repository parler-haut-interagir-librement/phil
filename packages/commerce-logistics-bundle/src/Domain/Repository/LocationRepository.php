<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use Phil\CommerceLogisticsBundle\Domain\Model\Location;

/**
 * Finds and stores locations. `Infrastructure\Doctrine\DoctrineLogisticsRepository` is the Doctrine adapter.
 */
interface LocationRepository
{
    public function findLocation(string $code): ?Location;

    /**
     * @return list<Location> the open locations, highest priority first, then by code
     */
    public function openLocations(): array;

    public function addLocation(Location $location): void;
}
