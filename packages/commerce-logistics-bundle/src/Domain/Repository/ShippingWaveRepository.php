<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use Phil\CommerceLogisticsBundle\Domain\Model\ShippingWave;
use Phil\CommerceLogisticsBundle\Domain\Model\WaveAssignment;

/**
 * Finds and stores shipping waves and the orders planned in them. `Infrastructure\Doctrine\DoctrineLogisticsRepository`
 * is the Doctrine adapter.
 */
interface ShippingWaveRepository
{
    public function findWave(string $code): ?ShippingWave;

    public function addWave(ShippingWave $wave): void;

    /**
     * @return list<ShippingWave> the waves not released yet, the earliest estimated first, then by code
     */
    public function plannedWaves(): array;

    public function assignmentOf(string $orderNumber): ?WaveAssignment;

    public function addAssignment(WaveAssignment $assignment): void;

    public function removeAssignment(WaveAssignment $assignment): void;

    /**
     * @return list<string> the numbers of the orders planned in the wave, in order
     */
    public function ordersOfWave(string $code): array;
}
