<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Memory;

use Phil\ProductBundles\Contract\BundleRepository;
use Phil\ProductBundles\Internal\Check;
use Phil\ProductBundles\ProductBundle;

/**
 * The bundles in an array, for tests and a single process.
 */
final class InMemoryBundles implements BundleRepository
{
    /** @var array<string, ProductBundle> */
    private array $bundles = [];

    public function find(string $sku): ?ProductBundle
    {
        return $this->bundles[$sku] ?? null;
    }

    public function save(ProductBundle $bundle): void
    {
        $this->bundles[$bundle->sku] = $bundle;
    }

    public function remove(string $sku): void
    {
        unset($this->bundles[$sku]);
    }

    public function containing(string $componentSku, int $limit = 100): array
    {
        Check::between('limit', $limit, 1, 1000);
        $skus = [];

        foreach ($this->bundles as $bundle) {
            if (0 < $bundle->quantityOf($componentSku)) {
                $skus[] = $bundle->sku;
            }
        }

        sort($skus, \SORT_STRING);

        return \array_slice($skus, 0, $limit);
    }
}
