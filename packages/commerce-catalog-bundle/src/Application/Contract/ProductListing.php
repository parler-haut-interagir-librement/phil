<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application\Contract;

use Phil\CommerceCatalogBundle\Application\ProductPage;
use SortDirection;

/**
 * Lists the products a channel shows, page by page, for a catalogue page or a feed of any length:
 * `Infrastructure\Doctrine\DbalProductListing` reads them on DBAL, by SKU, a cursor naming where the next page starts.
 *
 * A product is listed when it is active, in the channel's selection, and listed — neither left to its direct link nor
 * hidden —, and so is its family for a variant; each comes with the stricter of the two offers.
 */
interface ProductListing
{
    /** The products of a page, at most. */
    public const int MAX_LIMIT = 500;

    /**
     * @param string|null $channel  the channel whose selection is listed; none lists what every channel sells
     * @param string|null $after    the cursor of the page before, `ProductPage::$next`
     * @param int         $limit    the products of the page, 1 to `MAX_LIMIT`
     * @param string|null $locale   the locale of the names, along its fallback chain; none gives each product's own name
     * @param bool        $families whether families are listed beside their variants
     */
    public function page(?string $channel, ?string $after = null, int $limit = 50, ?string $locale = null, bool $families = true, SortDirection $direction = SortDirection::Ascending): ProductPage;
}
