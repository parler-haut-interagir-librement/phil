<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceCatalogBundle\Application\Contract\ProductListing;
use Phil\CommerceCatalogBundle\Application\ListedProduct;
use Phil\CommerceCatalogBundle\Application\ProductPage;
use SortDirection;

/**
 * A listing of products given in SKU order, which records what each page was asked with.
 */
final class InMemoryListing implements ProductListing
{
    /** @var list<array{?string, ?string, int, ?string, bool}> */
    public array $asked = [];

    /** @var list<ListedProduct> */
    private readonly array $products;

    public function __construct(ListedProduct ...$products)
    {
        $this->products = array_values($products);
    }

    #[Override]
    public function page(?string $channel, ?string $after = null, int $limit = 50, ?string $locale = null, bool $families = true, SortDirection $direction = SortDirection::Ascending): ProductPage
    {
        $this->asked[] = [$channel, $after, $limit, $locale, $families];
        $rest = array_values(array_filter($this->products, static fn (ListedProduct $product): bool => null === $after || strcmp($product->sku, $after) > 0));
        $page = \array_slice($rest, 0, $limit);

        return new ProductPage($page, \count($rest) > $limit ? $page[$limit - 1]->sku : null);
    }
}
