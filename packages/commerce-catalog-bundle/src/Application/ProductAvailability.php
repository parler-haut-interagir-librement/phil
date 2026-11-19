<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\SellableAvailability;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;

/**
 * The channels' selections and the offers of products, for the core and the pages: a product withdrawn, or outside a
 * channel's selection, is hidden there; otherwise its own offer holds — listed or by direct link only, orderable or
 * not, priced or not —, made stricter by its family's for a variant. The same answer goes to the product at its price,
 * at the price the buyer chose, and as a community copy. Other sellables, and products the catalogue does not know,
 * are left to others.
 */
final readonly class ProductAvailability implements SellableAvailability
{
    public function __construct(
        private ProductRepository $products,
    ) {
    }

    #[Override]
    public function offerThrough(SellableReference $reference, ?string $channel): Offer
    {
        $sku = ProductSellables::TYPE === $reference->type ? $reference->id : PriceRuleSellables::skuOf($reference);
        $product = null === $sku ? null : $this->products->find($sku);

        return $product instanceof Product ? $this->of($product, $channel) : Offer::full();
    }

    /**
     * What the channel offers of a product the page already holds: its own offer there, made stricter by its family's
     * for a variant. It reads the products as they are now: two calls may answer differently.
     *
     * @phpstan-impure
     */
    public function of(Product $product, ?string $channel): Offer
    {
        $offer = $product->offerThrough($channel);
        $family = null === $product->family() || Visibility::Hidden === $offer->visibility ? null : $this->products->find($product->family());

        return $family instanceof Product ? $offer->stricter($family->offerThrough($channel)) : $offer;
    }
}
