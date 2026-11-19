<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Phil\CommerceCatalogBundle\Domain\Model\Product;

/**
 * The names `ProductTexts` read for a list of products, in the locale asked, or each product's own.
 */
final readonly class ProductTextSet
{
    /**
     * @param array<string, string> $names by SKU
     */
    public function __construct(
        private array $names = [],
    ) {
    }

    public function name(Product $product): string
    {
        return $this->names[$product->sku] ?? $product->name();
    }

    /**
     * Whether the product is shown in a translation rather than in its own text.
     */
    public function isTranslated(Product $product): bool
    {
        return isset($this->names[$product->sku]);
    }
}
