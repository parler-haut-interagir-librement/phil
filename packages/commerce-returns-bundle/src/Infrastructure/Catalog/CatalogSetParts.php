<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Infrastructure\Catalog;

use Override;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\CompositeProducts;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\CommerceReturnsBundle\Application\Contract\SetParts;

/**
 * `SetParts` on `phil/commerce-catalog-bundle`: a set is a composite product of the catalogue, and its parts are
 * products, named `product:<sku>` as their own lines would be.
 */
final readonly class CatalogSetParts implements SetParts
{
    public function __construct(
        private CompositeProducts $composites,
    ) {
    }

    #[Override]
    public function partsOf(SellableReference $reference): array
    {
        $parts = [];

        foreach ($this->composites->partsOf($reference) as $component) {
            $parts[] = ProductSellables::TYPE.':'.$component->sku;
        }

        return $parts;
    }
}
