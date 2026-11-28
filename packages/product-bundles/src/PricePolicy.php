<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\DiscountCascade;
use Phil\Pricing\Rate;
use Phil\ProductBundles\Exception\InvalidBundle;
use Phil\ProductBundles\Internal\Check;
use RoundingMode;

/**
 * How a bundle is priced: a price of its own, or the price of its components less a rate.
 *
 * A discount is in parts per million, from 0 to 99.9999 %, rounded by a named `\RoundingMode`, half away from zero
 * unless another is given: the same components always give the same price.
 */
final readonly class PricePolicy
{
    private function __construct(
        public ?Money $fixed,
        public ?Rate $discount,
        public RoundingMode $rounding,
    ) {
    }

    /**
     * A price of its own, above zero, whatever its components cost apart.
     */
    public static function fixed(Money $price): self
    {
        if (!$price->isPositive()) {
            throw InvalidBundle::because('fixed price', 'is above zero');
        }

        return new self($price, null, RoundingMode::HalfAwayFromZero);
    }

    /**
     * The price of the components less a rate: `Rate::ofPercent('15')` takes 15 % off the sum.
     */
    public static function discountOnComponents(Rate $discount, RoundingMode $rounding = RoundingMode::HalfAwayFromZero): self
    {
        if ($discount->partsPerMillion < 0 || $discount->partsPerMillion >= Rate::ONE_MILLION) {
            throw InvalidBundle::because('discount', 'is from 0 to 99.9999 %');
        }

        return new self(null, $discount, $rounding);
    }

    public function isFixed(): bool
    {
        return $this->fixed instanceof Money;
    }

    /**
     * The bundle's price, given what its components sell for together.
     */
    public function priceOf(Money $componentsValue): Money
    {
        if ($this->fixed instanceof Money) {
            return $this->fixed;
        }

        return new DiscountCascade()->apply($componentsValue, [$this->discount ?? Rate::zero()], $this->rounding)->price;
    }

    /**
     * @return array{fixed: int|null, discount_ppm: int|null, rounding: string}
     */
    public function toArray(): array
    {
        return [
            'fixed' => $this->fixed?->minorUnits,
            'discount_ppm' => $this->discount?->partsPerMillion,
            'rounding' => $this->rounding->name,
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public static function fromArray(array $row, Currency $currency): self
    {
        $fixed = Check::nullableInt($row, 'fixed');
        $discount = Check::nullableInt($row, 'discount_ppm');
        $rounding = self::rounding(Check::string($row, 'rounding'));

        if ((null === $fixed) === (null === $discount)) {
            throw InvalidBundle::because('stored price policy', 'has a fixed price or a discount, one of the two');
        }

        if (null !== $fixed) {
            if (RoundingMode::HalfAwayFromZero !== $rounding) {
                throw InvalidBundle::because('stored price policy', 'rounds a fixed price half away from zero');
            }

            return self::fixed(Money::of(Check::between('stored fixed price', $fixed, 1, \PHP_INT_MAX), $currency));
        }

        return self::discountOnComponents(Rate::of(Check::between('stored discount', (int) $discount, 0, Rate::ONE_MILLION - 1)), $rounding);
    }

    private static function rounding(string $name): RoundingMode
    {
        foreach (RoundingMode::cases() as $mode) {
            if ($mode->name === $name) {
                return $mode;
            }
        }

        throw InvalidBundle::because('stored rounding mode', 'names a case of \RoundingMode');
    }
}
