<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\Money\Money;
use Phil\Money\SchemaOrg;

/**
 * A product as schema.org reads it, a `Product` with its `Offer`: name — the one given, in the page's locale, else its
 * own —, SKU, page, its attributes and its options as property values, the family of a variant, and the price — the
 * one given, from a price book or a promotion, else the base price — with whether it is still sold.
 */
final class ProductSchema
{
    /**
     * @param array<string, string>|null $attributes the attributes to show — a variant's with its family's, from
     *                                               `ProductVariants::attributesOf()` —; its own when not given
     *
     * @return array<string, mixed>
     */
    public static function of(Product $product, ?string $url = null, ?Money $price = null, ?string $name = null, ?array $attributes = null): array
    {
        $properties = [];

        foreach ([...$attributes ?? $product->attributes(), ...$product->options()] as $property => $value) {
            $properties[] = ['@type' => 'PropertyValue', 'name' => $property, 'value' => $value];
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name ?? $product->name(),
            'sku' => $product->sku,
            'inProductGroupWithID' => $product->family(),
            'url' => $url,
            'additionalProperty' => [] === $properties ? null : $properties,
            'offers' => array_filter([
                '@type' => 'Offer',
                ...SchemaOrg::price($price ?? $product->price),
                'availability' => 'https://schema.org/'.($product->isActive() ? 'InStock' : 'Discontinued'),
                'url' => $url,
            ], static fn (?string $value): bool => null !== $value),
        ], static fn (mixed $value): bool => null !== $value);
    }
}
