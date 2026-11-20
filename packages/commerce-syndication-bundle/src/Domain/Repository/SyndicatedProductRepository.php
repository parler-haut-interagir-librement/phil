<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Repository;

use Phil\CommerceSyndicationBundle\Domain\Model\SyndicatedProduct;

interface SyndicatedProductRepository
{
    public function findProduct(string $supplier, string $sku): ?SyndicatedProduct;

    public function addProduct(SyndicatedProduct $product): void;
}
