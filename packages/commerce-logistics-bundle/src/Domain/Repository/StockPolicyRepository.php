<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use Phil\CommerceLogisticsBundle\Domain\Model\StockPolicy;

/**
 * Finds and stores what each SKU does once its stock runs out. `Infrastructure\Doctrine\DoctrineLogisticsRepository`
 * is the Doctrine adapter.
 */
interface StockPolicyRepository
{
    public function policy(string $sku): ?StockPolicy;

    /**
     * @param list<string> $skus
     *
     * @return list<StockPolicy> the policies of these SKUs that exist, in no particular order
     */
    public function policiesOf(array $skus): array;

    public function addPolicy(StockPolicy $policy): void;
}
