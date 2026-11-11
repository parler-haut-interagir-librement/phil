<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

/**
 * Whether what an offer sells can be had: the `availability` of an `Offer`.
 */
enum ItemAvailability: string
{
    case InStock = 'https://schema.org/InStock';
    case OutOfStock = 'https://schema.org/OutOfStock';
    case SoldOut = 'https://schema.org/SoldOut';
    case PreOrder = 'https://schema.org/PreOrder';
    case PreSale = 'https://schema.org/PreSale';
    case BackOrder = 'https://schema.org/BackOrder';
    case LimitedAvailability = 'https://schema.org/LimitedAvailability';
    case OnlineOnly = 'https://schema.org/OnlineOnly';
    case InStoreOnly = 'https://schema.org/InStoreOnly';
    case Discontinued = 'https://schema.org/Discontinued';
}
