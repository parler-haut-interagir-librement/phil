<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;

/**
 * Tells the core what a product sells, for the references of type `product`: `product:<sku>`, at its base price, in
 * its tax category, described by its name in the buyer's locale when `ProductTexts` has it. A withdrawn product is not
 * sold, nor a family, which is chosen through its variants.
 */
final readonly class ProductSellables implements SellableResolver
{
    public const string TYPE = 'product';

    public function __construct(
        private ProductRepository $products,
        private ?ProductTexts $texts = null,
    ) {
    }

    #[Override]
    public function supports(string $type): bool
    {
        return self::TYPE === $type;
    }

    #[Override]
    public function resolve(SellableReference $reference): ?Sellable
    {
        $product = $this->products->find($reference->id);

        if (!$product instanceof Product || !$product->isActive() || $product->isFamily()) {
            return null;
        }

        $name = $this->texts?->of([$product])->name($product) ?? $product->name();

        return Sellable::of($reference, $name, $product->price, $product->taxCategory());
    }
}
