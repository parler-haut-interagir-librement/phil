<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceCatalogBundle\Application\Contract\ProductFamilies;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;

final class InMemoryProducts implements ProductRepository, ProductFamilies
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
    public function variantsOf(string $family): array
    {
        $variants = array_filter($this->products, static fn (Product $product): bool => $product->family() === $family);
        ksort($variants, \SORT_STRING);

        return array_values($variants);
    }

    #[Override]
    public function add(Product $product): void
    {
        $this->products[$product->sku] = $product;
    }
}
