<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\ProductBundles\Internal\Check;

/**
 * A product of a bundle and how many units of it one bundle holds.
 */
final readonly class BundleComponent
{
    public function __construct(
        public string $sku,
        public int $quantity = 1,
    ) {
        Check::sku('component SKU', $sku);
        Check::between('quantity of a component', $quantity, 1, Check::MAX_QUANTITY);
    }

    /**
     * @return array{sku: string, quantity: int}
     */
    public function toArray(): array
    {
        return ['sku' => $this->sku, 'quantity' => $this->quantity];
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(Check::string($row, 'sku'), Check::int($row, 'quantity'));
    }
}
