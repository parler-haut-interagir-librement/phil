<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * One part of a composite product: a product of the catalogue, by its SKU, and how many units of it the composite
 * holds — a rulebook and two dice sets in a starter box.
 */
final readonly class ProductComponent
{
    /** The units of one part, at most. */
    public const int MAX_QUANTITY = 1_000;

    private function __construct(
        public string $sku,
        public int $quantity,
    ) {
    }

    public static function of(string $sku, int $quantity = 1): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', $sku)) {
            throw InvalidValue::because(\sprintf('"%s" is not a SKU: up to 64 letters, digits, ".", "_" or "-", starting with a letter or digit.', $sku));
        }

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw InvalidValue::because(\sprintf('A part of a composite product holds 1 to %d units; %d is not.', self::MAX_QUANTITY, $quantity));
        }

        return new self($sku, $quantity);
    }

    /**
     * @return array{sku: string, quantity: int}
     */
    public function toArray(): array
    {
        return ['sku' => $this->sku, 'quantity' => $this->quantity];
    }

    /**
     * @param array{sku: string, quantity: int} $stored
     */
    public static function fromArray(array $stored): self
    {
        return self::of($stored['sku'], $stored['quantity']);
    }
}
