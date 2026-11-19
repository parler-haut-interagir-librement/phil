<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Repository;

use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;

/**
 * Finds and stores the quantity rules of SKUs. `Infrastructure\Doctrine\DoctrineWholesaleRepository` is the Doctrine
 * adapter.
 */
interface QuantityRuleRepository
{
    /**
     * @param string|null $customerGroup the rule for every group when null
     */
    public function findRule(string $sku, ?string $customerGroup): ?QuantityRule;

    /**
     * @return list<QuantityRule> the rules of the SKU, for every group and for each group, in one lookup
     */
    public function rulesOf(string $sku): array;

    public function addRule(QuantityRule $rule): void;

    public function removeRule(QuantityRule $rule): void;
}
