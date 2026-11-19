<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\Repository;

use Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign;

/**
 * Finds and stores designs. `Infrastructure\Doctrine\DoctrineProductDesignRepository` is the Doctrine adapter.
 */
interface ProductDesignRepository
{
    public function find(string $code): ?ProductDesign;

    public function add(ProductDesign $design): void;
}
