<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

/**
 * A page of a product list: the products shown, and the cursor that reads the next page — the last SKU read — or none
 * when the list is read to its end.
 */
final readonly class ProductPage
{
    /**
     * @param list<ListedProduct> $products
     */
    public function __construct(
        public array $products,
        public ?string $next,
    ) {
    }
}
