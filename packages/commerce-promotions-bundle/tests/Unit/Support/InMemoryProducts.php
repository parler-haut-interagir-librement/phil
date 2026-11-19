<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;

final class InMemoryProducts implements ProductRepository
{
    /** @var array<string, Product> */
    private array $products = [];

    public function __construct(Product ...$products)
    {
        foreach ($products as $product) {
            $this->add($product);
        }
    }

    #[Override]
    public function find(string $sku): ?Product
    {
        return $this->products[$sku] ?? null;
    }

    #[Override]
    public function add(Product $product): void
    {
        $this->products[$product->sku] = $product;
    }
}
