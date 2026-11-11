<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A `Product`: its name, picture, description, codes, brand — a `Brand` of that name when given as a text — and its
 * offers.
 */
final class Product
{
    /**
     * @param Node|Reference|list<Node|Reference>|null $offers
     * @param array<string, mixed>                     $properties the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $name,
        ?string $id = null,
        string|LanguageString|null $description = null,
        string|Node|Reference|null $image = null,
        ?string $sku = null,
        ?string $gtin = null,
        string|Node|Reference|null $brand = null,
        Node|Reference|array|null $offers = null,
        ?string $url = null,
        array $properties = [],
    ): Node {
        return Node::of('Product', Properties::merge($properties, [
            'name' => $name,
            'description' => $description,
            'image' => $image,
            'sku' => $sku,
            'gtin' => $gtin,
            'brand' => \is_string($brand) ? Node::of('Brand', ['name' => $brand]) : $brand,
            'offers' => $offers,
            'url' => $url,
        ]), $id);
    }
}
