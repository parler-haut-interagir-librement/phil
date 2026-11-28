<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductBundles\Exception\InvalidBundle;
use Phil\ProductBundles\Exception\ReturnRefused;
use Phil\ProductBundles\Internal\Check;

/**
 * A bundle as it was sold, frozen on its order line: its components, each one's share of the price and unit cost, the
 * quantity sold and the quantity returned.
 *
 * The line keeps what the sale was, whatever the bundle becomes afterwards: a return refunds what was paid and puts
 * back what was drawn. A bundle is returned whole, never a component alone.
 */
final readonly class SoldBundle
{
    /** @var list<SoldComponent> */
    public array $components;

    /**
     * @param list<SoldComponent> $components
     */
    public function __construct(
        public string $sku,
        public BundleKind $kind,
        public Money $unitPrice,
        array $components,
        public int $quantity,
        public int $returned = 0,
    ) {
        Check::sku('bundle SKU', $sku);
        Check::between('number of components', \count($components), 1, Check::MAX_COMPONENTS);
        Check::between('quantity sold', $quantity, 1, Check::MAX_QUANTITY);
        Check::between('quantity returned', $returned, 0, $quantity);

        if ($unitPrice->isNegative()) {
            throw InvalidBundle::because('unit price of "'.$sku.'"', 'is zero or more');
        }

        $shares = [];
        $seen = [];

        foreach ($components as $component) {
            Check::sku('component SKU', $component->sku);
            Check::between('quantity of a component', $component->perBundle, 1, Check::MAX_QUANTITY);

            if (\in_array($component->sku, $seen, true) || $component->sku === $sku) {
                throw InvalidBundle::because('component "'.$component->sku.'"', 'appears once in a bundle, never the bundle itself');
            }

            if (!$component->unitShare->currency->equals($unitPrice->currency) || $component->unitShare->isNegative()) {
                throw InvalidBundle::because('share of "'.$component->sku.'"', "is zero or more, in the price's currency");
            }

            $seen[] = $component->sku;
            $shares[] = $component->unitShare;
        }

        if (!Money::sum(...$shares)->equals($unitPrice)) {
            throw InvalidBundle::because('shares of "'.$sku.'"', 'add up to its unit price');
        }

        $this->components = $components;
    }

    /**
     * Freezes a quote for a sale of some bundles, with the composed cost when there is one.
     */
    public static function of(ProductBundle $bundle, BundleQuote $quote, int $quantity, ?BundleCost $cost = null): self
    {
        $components = [];

        foreach ($quote->components as $index => $component) {
            $components[] = new SoldComponent($component->sku, $component->quantity, $component->share, $cost?->components[$index]->unitCost);
        }

        return new self($bundle->sku, $bundle->kind, $quote->price, $components, $quantity);
    }

    public function total(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    /**
     * What one bundle cost the shop at the sale; null when a component's cost was unknown.
     */
    public function unitCost(): ?Money
    {
        $costs = [];

        foreach ($this->components as $component) {
            if (!$component->unitCost instanceof Money) {
                return null;
            }

            $costs[] = $component->unitCost->multiply($component->perBundle);
        }

        return Money::sum(...$costs);
    }

    /**
     * The total shared among the components, in the bundle's order: what a collection is taxed on.
     *
     * @return list<Money>
     */
    public function shares(): array
    {
        return $this->sharesOf($this->total());
    }

    /**
     * The units of each component the sale draws from stock.
     *
     * @return list<StockDraw>
     */
    public function draws(): array
    {
        return $this->drawsOf($this->quantity);
    }

    public function returnable(): int
    {
        return $this->quantity - $this->returned;
    }

    /**
     * Returns whole bundles: their price refunded, their components put back.
     *
     * @throws ReturnRefused when fewer than one bundle, or more than are left, are returned
     */
    public function refund(int $bundles): BundleRefund
    {
        if ($bundles < 1) {
            throw ReturnRefused::because($this->sku, 'one bundle or more is returned, whole');
        }

        if ($bundles > $this->returnable()) {
            throw ReturnRefused::because($this->sku, \sprintf('%d left to return, %d asked', $this->returnable(), $bundles));
        }

        $amount = $this->unitPrice->multiply($bundles);

        return new BundleRefund(
            $bundles,
            $amount,
            $this->sharesOf($amount),
            $this->drawsOf($bundles),
            new self($this->sku, $this->kind, $this->unitPrice, $this->components, $this->quantity, $this->returned + $bundles),
        );
    }

    /**
     * Scalars and nulls only, for the order line's column.
     *
     * @return array{sku: string, kind: string, currency: string, unit_price: int, quantity: int, returned: int, components: list<array{sku: string, per_bundle: int, unit_share: int, unit_cost: int|null, cost_currency: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'kind' => $this->kind->value,
            'currency' => $this->unitPrice->currency->code,
            'unit_price' => $this->unitPrice->minorUnits,
            'quantity' => $this->quantity,
            'returned' => $this->returned,
            'components' => array_map(static fn (SoldComponent $component): array => [
                'sku' => $component->sku,
                'per_bundle' => $component->perBundle,
                'unit_share' => $component->unitShare->minorUnits,
                'unit_cost' => $component->unitCost?->minorUnits,
                'cost_currency' => $component->unitCost?->currency->code,
            ], $this->components),
        ];
    }

    /**
     * Reads back what `toArray()` gave, refusing a row out of shape with `InvalidBundle`.
     *
     * @param array<array-key, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $currency = Check::currency(Check::string($row, 'currency'));
        $kind = BundleKind::tryFrom(Check::string($row, 'kind')) ?? throw InvalidBundle::because('stored kind', 'is "assembled" or "collection"');
        $components = array_map(
            static fn (mixed $component): SoldComponent => self::component(Check::row($component, 'stored component'), $currency),
            Check::list($row, 'components', Check::MAX_COMPONENTS),
        );

        return new self(
            Check::string($row, 'sku'),
            $kind,
            Check::money($row, 'unit_price', $currency),
            $components,
            Check::int($row, 'quantity'),
            Check::int($row, 'returned'),
        );
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function component(array $row, Currency $currency): SoldComponent
    {
        $costCurrency = $row['cost_currency'] ?? null;

        if ((null === $costCurrency) !== (null === ($row['unit_cost'] ?? null))) {
            throw InvalidBundle::because('stored unit cost', 'has an amount and a currency, or neither');
        }

        return new SoldComponent(
            Check::string($row, 'sku'),
            Check::int($row, 'per_bundle'),
            Check::money($row, 'unit_share', $currency),
            null === $costCurrency ? null : Check::money($row, 'unit_cost', $costCurrency === $currency->code ? $currency : Check::currency(Check::string($row, 'cost_currency'))),
        );
    }

    /**
     * @return list<Money>
     */
    private function sharesOf(Money $amount): array
    {
        $ratios = array_map(static fn (SoldComponent $component): int => $component->unitShare->minorUnits, $this->components);

        if (0 === array_sum($ratios)) {
            $ratios = array_map(static fn (SoldComponent $component): int => $component->perBundle, $this->components);
        }

        return $amount->allocate(...$ratios);
    }

    /**
     * @return list<StockDraw>
     */
    private function drawsOf(int $bundles): array
    {
        return array_map(static fn (SoldComponent $component): StockDraw => new StockDraw($component->sku, $component->perBundle * $bundles), $this->components);
    }
}
