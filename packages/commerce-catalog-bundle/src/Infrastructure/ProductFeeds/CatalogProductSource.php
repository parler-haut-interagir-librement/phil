<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds;

use DateTimeImmutable;
use Generator;
use Override;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\Contract\ProductListing;
use Phil\CommerceCatalogBundle\Application\ListedProduct;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\Money\Money;
use Phil\ProductFeeds\Availability;
use Phil\ProductFeeds\Condition;
use Phil\ProductFeeds\Contract\ProductSource;
use Phil\ProductFeeds\Exception\InvalidFeedItem;
use Phil\ProductFeeds\FeedItem;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Psr\Log\LoggerInterface;

/**
 * The catalogue as the source of a feed: the products and variants a channel lists, read page by page from
 * `ProductListing`, families left out — their variants carry the family's SKU as their group. A product left to its
 * direct link, or hidden, is never listed; a product listed without its price is left out, since a feed gives each
 * item's price.
 *
 * - The **price** is the base price; the price books' price for one unit, no customer group, the feed's channel and
 *   the moment of the feed, when lower, is the **sale price**.
 * - A product no order may hold is **out of stock**; otherwise the `availability` attribute, when it names one, or in stock.
 * - The **link** and the **image** come from the definition's templates; an `image` attribute holding an address wins.
 * - The other fields read the product's attributes, a variant's axes first, under the names the definition gives.
 *
 * A product whose fields a feed cannot carry — a GTIN whose check digit fails, an address refused — is left out and
 * logged, so that one product never stops the feed.
 */
final readonly class CatalogProductSource implements ProductSource
{
    /** The products read per page. */
    public const int PAGE = 200;

    public function __construct(
        private ProductListing $listing,
        private FeedDefinition $definition,
        private DateTimeImmutable $at,
        private ?UnitPriceResolver $prices = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return Generator<int, FeedItem>
     */
    #[Override]
    public function items(): Generator
    {
        $after = null;

        do {
            $page = $this->listing->page($this->definition->salesChannel, $after, self::PAGE, $this->definition->locale, families: false);

            foreach ($page->products as $product) {
                $item = $product->offer->priceShown ? $this->item($product) : null;

                if ($item instanceof FeedItem) {
                    yield $item;
                }
            }

            $after = $page->next;
        } while (null !== $after);
    }

    private function item(ListedProduct $product): ?FeedItem
    {
        try {
            return new FeedItem(
                $product->sku,
                $product->name,
                $this->url(FeedDefinition::address($this->definition->productLink, $product->sku, $product->family)),
                $product->price,
                $this->availability($product),
                description: $this->value($product, 'description'),
                imageLink: $this->image($product),
                salePrice: $this->salePrice($product),
                condition: Condition::tryFrom((string) $this->value($product, 'condition')) ?? Condition::New,
                brand: $this->value($product, 'brand'),
                gtin: $this->value($product, 'gtin'),
                mpn: $this->value($product, 'mpn'),
                itemGroupId: $product->family,
                color: $this->value($product, 'color'),
                size: $this->value($product, 'size'),
                material: $this->value($product, 'material'),
                pattern: $this->value($product, 'pattern'),
                productType: $this->value($product, 'product_type'),
            );
        } catch (InvalidFeedItem|InvalidUrl $exception) {
            $this->logger?->warning('The product {sku} is left out of the feed {feed}: {reason}', ['sku' => $product->sku, 'feed' => $this->definition->name, 'reason' => $exception->getMessage()]);

            return null;
        }
    }

    private function availability(ListedProduct $product): Availability
    {
        if (!$product->offer->orderable) {
            return Availability::OutOfStock;
        }

        $availability = Availability::tryFrom((string) $this->value($product, 'availability')) ?? Availability::InStock;

        // A date only comes with a preorder or a backorder, and the catalogue holds none: those are written out of stock.
        return $availability->takesADate() ? Availability::OutOfStock : $availability;
    }

    private function image(ListedProduct $product): ?Url
    {
        $image = $this->value($product, 'image');

        if (null !== $image) {
            return $this->url($image);
        }

        return null === $this->definition->imageLink ? null : $this->url(FeedDefinition::address($this->definition->imageLink, $product->sku, $product->family));
    }

    private function salePrice(ListedProduct $product): ?Money
    {
        $sellable = Sellable::of(SellableReference::of(ProductSellables::TYPE, $product->sku), $product->name, $product->price, $product->taxCategory);
        $price = $this->prices?->priceFor($sellable, PricingContext::of(1, $this->at, null, $this->definition->salesChannel));

        return $price instanceof Money && $price->currency->equals($product->price->currency) && $price->isLessThan($product->price) ? $price : null;
    }

    private function value(ListedProduct $product, string $field): ?string
    {
        return $product->value($this->definition->attributes[$field] ?? $field);
    }

    private function url(string $address): Url
    {
        return Url::fromString($address, $this->definition->allowLocalhost);
    }
}
