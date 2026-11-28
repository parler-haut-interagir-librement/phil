<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Contract;

use Phil\ProductBundles\ProductBundle;

/**
 * Keeps the bundles, by SKU.
 */
interface BundleRepository
{
    public function find(string $sku): ?ProductBundle;

    /**
     * Saves the bundle over the one of the same SKU.
     */
    public function save(ProductBundle $bundle): void;

    public function remove(string $sku): void;

    /**
     * The SKUs of the bundles that hold a component, in order, at most `$limit`: what to look at again when its
     * price, stock or cost changes.
     *
     * @param int $limit 1 to 1 000
     *
     * @return list<string>
     */
    public function containing(string $componentSku, int $limit = 100): array;
}
