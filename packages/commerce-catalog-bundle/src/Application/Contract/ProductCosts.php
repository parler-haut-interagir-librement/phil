<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application\Contract;

use Phil\Money\Money;

/**
 * What one unit of a product of the catalogue costs the shop: a composite product, the sum of its parts' costs, each
 * times its units. A bundle of products, a margin check or a report reads it without writing anything.
 * `Application\CompositeCosts` implements it.
 */
interface ProductCosts
{
    /**
     * @return Money|null the cost of one unit; null when the cost of the product, or of one of its parts, is not known,
     *                    or for a family, which is never sold itself
     */
    public function costOf(string $sku): ?Money;
}
