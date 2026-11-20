<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;

/**
 * Finds and stores delivery methods. `Infrastructure\Doctrine\DoctrineLogisticsRepository` is the Doctrine adapter.
 */
interface DeliveryMethodRepository
{
    public function findMethod(string $code): ?DeliveryMethod;

    /**
     * @return list<DeliveryMethod> the methods not withdrawn, highest priority first, then by code
     */
    public function activeMethods(): array;

    public function addMethod(DeliveryMethod $method): void;
}
