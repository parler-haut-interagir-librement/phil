<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Catalogue;

/**
 * The order a catalogue lists its courses in: the order its editors placed them in, by title, the newest course first,
 * or the free courses first and then by price, each currency together.
 */
enum CatalogueSort: string
{
    case Placed = 'placed';
    case Title = 'title';
    case Newest = 'newest';
    case Price = 'price';
}
