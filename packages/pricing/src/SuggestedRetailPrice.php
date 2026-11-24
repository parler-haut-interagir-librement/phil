<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Pricing\Contract\PriceEndingRule;
use Phil\Pricing\Exception\InvalidPricing;
use Phil\Pricing\Exception\InvalidRate;
use RoundingMode;

/**
 * A suggested retail price: the price a supplier recommends, or the one a markup gives on a cost or on a wholesale
 * price, with where it comes from.
 *
 * A suggested price is always above zero. A price computed by a markup rounds away from zero unless a `\RoundingMode`
 * is given, as `PriceCalculator` does: the markup is always reached. The price may then be ended by any
 * `PriceEndingRule`; the price before the ending is kept.
 *
 * The library computes and compares; keeping a suggested price on a product is the catalogue's.
 */
final readonly class SuggestedRetailPrice
{
    /**
     * The markup of keystone pricing, 100 %: the price is twice its basis.
     */
    public const int KEYSTONE_MARKUP = Rate::ONE_MILLION;

    /**
     * @param Money|null $basis  the cost or the wholesale price marked up; null when the supplier gave the price
     * @param Rate|null  $markup the markup on the basis; null when the supplier gave the price
     */
    private function __construct(
        public Money $price,
        public Money $unendedPrice,
        public SuggestedRetailPriceSource $source,
        public ?Money $basis,
        public ?Rate $markup,
    ) {
    }

    /**
     * The price the supplier suggests.
     *
     * @throws InvalidPricing when the price is not above zero
     */
    public static function fromSupplier(Money $price): self
    {
        if (!$price->isPositive()) {
            throw InvalidPricing::suggestedPriceNotPositive($price->toDecimal());
        }

        return new self($price, $price, SuggestedRetailPriceSource::Supplier, null, null);
    }

    /**
     * The cost marked up: `price = cost × (1 + markup)`. A cost of 6.00 at a 60 % markup suggests 9.60.
     *
     * @throws InvalidPricing             when the cost is zero or negative, or the price rounds to zero
     * @throws InvalidRate                when the markup is -100 % or less
     * @throws Exception\AmountOutOfRange when the cost times the markup leaves the native range
     */
    public static function fromCost(Money $cost, Rate $markup, RoundingMode $mode = RoundingMode::AwayFromZero): self
    {
        return self::markedUp($cost, $markup, $mode, SuggestedRetailPriceSource::CostMarkup);
    }

    /**
     * Keystone pricing: twice the cost, a markup of 100 %. A cost of 6.00 suggests 12.00.
     *
     * @throws InvalidPricing when the cost is zero or negative
     */
    public static function keystone(Money $cost): self
    {
        return self::fromCost($cost, Rate::of(self::KEYSTONE_MARKUP));
    }

    /**
     * The wholesale price marked up by the retailer: `price = wholesale × (1 + markup)`. A wholesale price of 7.80
     * at a 100 % markup suggests 15.60.
     *
     * @throws InvalidPricing             when the wholesale price is zero or negative, or the price rounds to zero
     * @throws InvalidRate                when the markup is -100 % or less
     * @throws Exception\AmountOutOfRange when the wholesale price times the markup leaves the native range
     */
    public static function fromWholesale(Money $wholesale, Rate $markup, RoundingMode $mode = RoundingMode::AwayFromZero): self
    {
        return self::markedUp($wholesale, $markup, $mode, SuggestedRetailPriceSource::WholesaleMarkup);
    }

    /**
     * Whether the price is twice the cost it came from: a cost markup of 100 %.
     */
    public function isKeystone(): bool
    {
        return self::KEYSTONE_MARKUP === $this->markup?->partsPerMillion && SuggestedRetailPriceSource::CostMarkup === $this->source;
    }

    /**
     * The same suggested price, ended by `$rule`: 15.60 ended on .99 is 15.99. The ending starts from the price
     * before any earlier ending, and never goes below `$floor`; without one, below the cost or the wholesale price
     * the price came from.
     *
     * @throws CurrencyMismatch when the rule or the floor differs in currency
     * @throws InvalidPricing   when the rule ends the price at zero
     */
    public function ended(PriceEndingRule $rule, ?Money $floor = null): self
    {
        $ended = $rule->apply($this->unendedPrice, $floor ?? $this->basis);

        if (!$ended->isPositive()) {
            throw InvalidPricing::suggestedPriceNotPositive($ended->toDecimal());
        }

        return new self($ended, $this->unendedPrice, $this->source, $this->basis, $this->markup);
    }

    /**
     * A selling price compared to this suggested price: the difference, and the rate below or above it.
     *
     * @throws CurrencyMismatch when the selling price is in another currency
     * @throws InvalidPricing   when the selling price is negative
     */
    public function compare(Money $sellingPrice): RetailPriceComparison
    {
        return RetailPriceComparison::of($sellingPrice, $this->price);
    }

    private static function markedUp(Money $basis, Rate $markup, RoundingMode $mode, SuggestedRetailPriceSource $source): self
    {
        $price = MarkedUpPrice::of($basis, $markup, $mode);

        if (!$price->isPositive()) {
            throw InvalidPricing::suggestedPriceNotPositive($price->toDecimal());
        }

        return new self($price, $price, $source, $basis, $markup);
    }
}
