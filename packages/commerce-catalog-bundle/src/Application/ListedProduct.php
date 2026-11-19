<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\Money\Money;

/**
 * A product as a list shows it, read without its entity: its name in the locale asked, its base price, its attributes,
 * its family and options for a variant, its axes for a family, and what the channel offers of it — listed always,
 * orderable or not, its price shown or not.
 */
final readonly class ListedProduct
{
    /**
     * @param array<string, string> $attributes attribute → value, by attribute
     * @param array<string, string> $options    axis → value, for a variant, in no particular order
     * @param list<string>          $axes       the axes of a family's variants
     */
    public function __construct(
        public string $sku,
        public ProductKind $kind,
        public string $name,
        public Money $price,
        public string $taxCategory,
        public Offer $offer,
        public array $attributes = [],
        public ?string $family = null,
        public array $options = [],
        public array $axes = [],
    ) {
    }

    /**
     * Whether it is a family, chosen through its variants and never sold itself.
     */
    public function isFamily(): bool
    {
        return [] !== $this->axes;
    }

    /**
     * The same product under another name: its name in a locale.
     */
    public function named(string $name): self
    {
        return clone ($this, ['name' => $name]);
    }

    /**
     * The value of a variant's axis, else of an attribute, by that name.
     */
    public function value(string $name): ?string
    {
        return $this->options[$name] ?? $this->attributes[$name] ?? null;
    }
}
