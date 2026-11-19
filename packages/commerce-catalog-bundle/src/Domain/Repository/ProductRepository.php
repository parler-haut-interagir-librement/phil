<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\Repository;

use Phil\CommerceCatalogBundle\Domain\Model\Product;

/**
 * Finds and stores products. `Infrastructure\Doctrine\DoctrineProductRepository` is the Doctrine adapter.
 */
interface ProductRepository
{
    public function find(string $sku): ?Product;

    public function add(Product $product): void;
}
