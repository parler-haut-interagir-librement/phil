<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * A product as a supplier describes it to a reseller: its SKU and name, the retail price to sell it at, what a unit
 * costs the reseller, its tax category, how many are available, and whether it is still sold.
 */
final readonly class FeedItem
{
    private function __construct(
        public string $sku,
        public string $name,
        public Money $retail,
        public Money $cost,
        public string $taxCategory,
        public int $available,
        public bool $active,
    ) {
    }

    public static function of(string $sku, string $name, Money $retail, Money $cost, string $taxCategory, int $available, bool $active = true): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $sku)) {
            throw InvalidValue::because(\sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', $sku));
        }

        if ('' === trim($name) || mb_strlen($name) > 255 || '' === $taxCategory || \strlen($taxCategory) > 64) {
            throw InvalidValue::because(\sprintf('The product %s has a name of 1 to 255 characters and a tax category.', $sku));
        }

        if (!$cost->currency->equals($retail->currency) || $cost->isNegative() || $cost->isGreaterThan($retail)) {
            throw InvalidValue::because(\sprintf('The product %s costs zero or more, at most its retail price, in its currency.', $sku));
        }

        if ($available < 0) {
            throw InvalidValue::because(\sprintf('The product %s has zero units available or more.', $sku));
        }

        return new self($sku, $name, $retail, $cost, $taxCategory, $available, $active);
    }
}
