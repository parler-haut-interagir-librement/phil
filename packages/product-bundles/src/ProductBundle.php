<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductBundles\Exception\InvalidBundle;
use Phil\ProductBundles\Internal\Check;

/**
 * Products sold together under a SKU of their own: from one to 50 components, each SKU once, never the bundle itself,
 * priced in one currency.
 *
 * A bundle holds no stock: what it can sell follows from its components' stock, and what it costs from theirs. A
 * bundle is immutable; changing its components or its price makes a new one, saved over the old.
 */
final readonly class ProductBundle
{
    /** @var list<BundleComponent> */
    public array $components;

    /**
     * @param list<BundleComponent> $components
     */
    public function __construct(
        public string $sku,
        public BundleKind $kind,
        public Currency $currency,
        array $components,
        public PricePolicy $price,
    ) {
        Check::sku('bundle SKU', $sku);
        Check::between('number of components', \count($components), 1, Check::MAX_COMPONENTS);
        $seen = [];

        foreach ($components as $component) {
            if ($component->sku === $sku) {
                throw InvalidBundle::because('bundle "'.$sku.'"', 'is not one of its own components');
            }

            if (\in_array($component->sku, $seen, true)) {
                throw InvalidBundle::because('component "'.$component->sku.'"', 'appears once in a bundle; raise its quantity instead');
            }

            $seen[] = $component->sku;
        }

        if ($price->fixed instanceof Money && !$price->fixed->currency->equals($currency)) {
            throw InvalidBundle::because('fixed price of "'.$sku.'"', "is in the bundle's currency, ".$currency->code);
        }

        $this->components = $components;
    }

    /**
     * How many units of a SKU one bundle holds, 0 when it is not a component.
     */
    public function quantityOf(string $sku): int
    {
        foreach ($this->components as $component) {
            if ($component->sku === $sku) {
                return $component->quantity;
            }
        }

        return 0;
    }

    /**
     * Scalars and nulls only, for a column or a document.
     *
     * @return array{sku: string, kind: string, currency: string, components: list<array{sku: string, quantity: int}>, price: array{fixed: int|null, discount_ppm: int|null, rounding: string}}
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'kind' => $this->kind->value,
            'currency' => $this->currency->code,
            'components' => array_map(static fn (BundleComponent $component): array => $component->toArray(), $this->components),
            'price' => $this->price->toArray(),
        ];
    }

    /**
     * Reads back what `toArray()` gave, refusing a row out of shape with `InvalidBundle`.
     *
     * @param array<array-key, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $kind = BundleKind::tryFrom(Check::string($row, 'kind')) ?? throw InvalidBundle::because('stored kind', 'is "assembled" or "collection"');
        $currency = Check::currency(Check::string($row, 'currency'));
        $components = array_map(
            static fn (mixed $component): BundleComponent => BundleComponent::fromArray(Check::row($component, 'stored component')),
            Check::list($row, 'components', Check::MAX_COMPONENTS),
        );

        return new self(
            Check::string($row, 'sku'),
            $kind,
            $currency,
            $components,
            PricePolicy::fromArray(Check::row($row['price'] ?? null, 'stored price policy'), $currency),
        );
    }
}
