<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\SellableAvailability;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceSyndicationBundle\Domain\Model\SyndicatedProduct;
use Phil\CommerceSyndicationBundle\Domain\Repository\SyndicatedProductRepository;

/**
 * Sells a supplier's products here, at the retail price of its last feed: the sellable type `syndicated`, whose
 * identifier is `supplier:sku`. A product withdrawn or out of stock at the supplier
 * is known, but sold through no channel.
 */
final readonly class SyndicatedSellables implements SellableResolver, SellableAvailability
{
    public const string TYPE = 'syndicated';

    public function __construct(
        private SyndicatedProductRepository $products,
    ) {
    }

    /**
     * The supplier and the SKU of a syndicated sellable's identifier; a partner code holds no colon.
     *
     * @return array{string, string}
     */
    public static function split(string $id): array
    {
        $parts = explode(':', $id, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    public static function reference(string $supplier, string $sku): SellableReference
    {
        return SellableReference::of(self::TYPE, $supplier.':'.$sku);
    }

    #[Override]
    public function supports(string $type): bool
    {
        return self::TYPE === $type;
    }

    #[Override]
    public function resolve(SellableReference $reference): ?Sellable
    {
        if (self::TYPE !== $reference->type) {
            return null;
        }

        [$supplier, $sku] = self::split($reference->id);
        $product = $this->products->findProduct($supplier, $sku);

        return $product instanceof SyndicatedProduct ? Sellable::of($reference, $product->name(), $product->retail, $product->taxCategory()) : null;
    }

    /**
     * Every channel offers a syndicated product in full while the supplier sells it and has some in stock, and hides
     * it otherwise; any other type is not this module's to restrict.
     */
    #[Override]
    public function offerThrough(SellableReference $reference, ?string $channel): Offer
    {
        if (self::TYPE !== $reference->type) {
            return Offer::full();
        }

        [$supplier, $sku] = self::split($reference->id);

        return false === $this->products->findProduct($supplier, $sku)?->isSold() ? Offer::hidden() : Offer::full();
    }
}
