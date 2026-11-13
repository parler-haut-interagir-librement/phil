<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Image\Image;

/**
 * One product label: the name, a detail — a size, a colour, a material —, the price as the application formats it,
 * the SKU, and an image of its code — a barcode or a QR code the application draws.
 */
final readonly class ProductLabel
{
    public function __construct(
        public string $name,
        public string $price,
        public ?string $sku = null,
        public ?string $detail = null,
        public ?Image $code = null,
    ) {
        if ('' === trim($name) || '' === trim($price)) {
            throw InvalidDocument::because('A product label has a name and a price.');
        }
    }
}
