<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application\Contract;

use Phil\CommerceCatalogBundle\Domain\Model\Product;

/**
 * Lists the variants of a family. `Infrastructure\Doctrine\DoctrineProductRepository` implements it beside
 * `ProductRepository`.
 */
interface ProductFamilies
{
    /**
     * @return list<Product> the variants of a family, withdrawn ones included, by SKU
     */
    public function variantsOf(string $family): array;
}
