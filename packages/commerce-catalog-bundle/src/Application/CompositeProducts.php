<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\CommerceCatalogBundle\Domain\ValueObject\ProductComponent;

/**
 * Composite products: a product made of others, sold as one product on one line, at its own price and in its own tax
 * category, the whole of it taxed together. Its parts are products of the catalogue that are neither families nor
 * composites themselves, so a composite is one level deep.
 *
 * The parts are what the stock gives out and what a return takes back whole: `partsOf()` reads them from an order
 * line's reference.
 */
final readonly class CompositeProducts
{
    public function __construct(
        private ProductRepository $products,
    ) {
    }

    /**
     * Makes the product a composite of these parts, each checked against the catalogue; none makes it a product of
     * its own again.
     *
     * @param list<ProductComponent> $components
     */
    public function compose(Product $product, array $components): void
    {
        foreach ($components as $component) {
            $part = $this->products->find($component->sku);

            if (!$part instanceof Product) {
                throw CatalogRefused::unknownPart($product->sku, $component->sku);
            }

            if ($part->isFamily() || $part->isComposite()) {
                throw CatalogRefused::nestedPart($product->sku, $component->sku);
            }
        }

        $product->composeOf($components);
    }

    /**
     * The parts a line of this sellable holds per unit; none for what is no composite product of the catalogue.
     *
     * @return list<ProductComponent>
     */
    public function partsOf(SellableReference $reference): array
    {
        if (ProductSellables::TYPE !== $reference->type) {
            return [];
        }

        return $this->products->find($reference->id)?->components() ?? [];
    }
}
