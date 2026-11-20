<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * One price of a price book: the unit price of a SKU from a quantity on the line.
 */
final readonly class PriceTier
{
    private function __construct(
        public string $sku,
        public int $minQuantity,
        public Money $unitPrice,
    ) {
    }

    public static function of(string $sku, int $minQuantity, Money $unitPrice): self
    {
        if ('' === $sku) {
            throw InvalidValue::because('A price names its SKU.');
        }

        if ($minQuantity < 1) {
            throw InvalidValue::because(\sprintf('A price applies from 1 unit at least; %d is not.', $minQuantity));
        }

        if ($unitPrice->isNegative()) {
            throw InvalidValue::because(\sprintf('The price of %s is negative.', $sku));
        }

        return new self($sku, $minQuantity, $unitPrice);
    }
}
