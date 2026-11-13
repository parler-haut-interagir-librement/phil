<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Image\Image;

/**
 * One shelf label: the product's name, its price in large figures, the price by unit where the law asks for it, the
 * SKU, where it is shelved, and an image of its code.
 */
final readonly class ShelfLabel
{
    public function __construct(
        public string $name,
        public string $price,
        public ?string $unitPrice = null,
        public ?string $sku = null,
        public ?string $location = null,
        public ?Image $code = null,
    ) {
        if ('' === trim($name) || '' === trim($price)) {
            throw InvalidDocument::because('A shelf label has a name and a price.');
        }
    }
}
