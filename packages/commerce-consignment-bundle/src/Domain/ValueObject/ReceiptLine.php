<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * One line of a receipt: units of a SKU either bought, at a unit cost, or left on consignment by a consignor, on terms.
 * One receipt may hold both for the same SKU — five copies bought and five consigned of the same book.
 */
final readonly class ReceiptLine
{
    private function __construct(
        public string $sku,
        public int $quantity,
        public Owner $owner,
        public ?Money $unitCost,
        public ?ConsignmentTerms $terms,
    ) {
    }

    public static function purchased(string $sku, int $quantity, Money $unitCost): self
    {
        if ($unitCost->isNegative()) {
            throw InvalidValue::because(\sprintf('A unit cost is zero or more; %s is not.', $unitCost->toDecimal()));
        }

        return new self(InvalidValue::checkIdentifier($sku, 'SKU'), InvalidValue::checkQuantity($quantity), Owner::shop(), $unitCost, null);
    }

    public static function consigned(string $sku, int $quantity, string $consignor, ConsignmentTerms $terms): self
    {
        return new self(InvalidValue::checkIdentifier($sku, 'SKU'), InvalidValue::checkQuantity($quantity), Owner::consignor($consignor), null, $terms);
    }
}
