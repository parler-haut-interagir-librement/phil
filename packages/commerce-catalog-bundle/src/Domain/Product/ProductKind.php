<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\Product;

/**
 * What a product is: something shipped, something downloaded, or something done.
 */
enum ProductKind: string
{
    /** Shipped or picked up: the logistics module counts its stock. */
    case Physical = 'physical';

    /** Downloaded or unlocked: nothing to ship. */
    case Digital = 'digital';

    /** Done for the buyer: a repair, a lesson, a booking. */
    case Service = 'service';
}
