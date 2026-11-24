# Changelog

All notable changes to `phil/pricing` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.0-alpha.

### Added

- `Rate`: parts per million; `of()`, `zero()`, `ofBasisPoints()`, `ofPercent()` with up to four decimals and a sign,
  `percent()`, `isNegative()`, `equals()`, `compare()`.
- `MarginCalculator`: `margin()`, `marginRate()` and `markupRate()` of a price and a cost, rounded half away from zero
  or by any `\RoundingMode`; `markupFromMargin()` and `marginFromMarkup()`; `summarize()` of one or more lines.
- `PricedLine` (unit price, unit cost, quantity; revenue, total cost, margin), refusing a price and a cost of opposite
  signs, and `MarginSummary`, whose rates are computed on the totals.
- `PriceCalculator`: `priceForMargin()`, `priceForMarkup()` and `priceForCostShare()`, rounded away from zero so the
  target is always reached.
- `DiscountCascade`: successive discount rates in the order given, each step rounded on its own; `DiscountedPrice`
  with its `DiscountStep`s, `totalDiscount()` and `effectiveRate()`.
- `PriceEndingRule`, `PriceEnding` (a step and an ending such as .99, .95 or a whole unit; up, nearest or down by
  `\RoundingMode`; never below zero or a floor) and `PriceEndingBands` (one rule per price band).
- `SuggestedRetailPrice`, always above zero, with its `SuggestedRetailPriceSource`: `fromSupplier()`, `fromCost()` by a
  markup rounded away from zero or by any `\RoundingMode`, `keystone()` (a markup of 100 %, `isKeystone()`) and
  `fromWholesale()`; `ended()` by any `PriceEndingRule`, keeping the unended price and never going below the cost or
  wholesale price it came from; `compare()` to a selling price.
- `RetailPriceComparison`: `difference()`, the signed `rate()` and the positive `rateBelow()` of a selling price
  against a suggested price, `isBelow()`, `isAbove()` and `isAtSuggestedPrice()`, refusing another currency and a
  negative selling price.
- `WholesalePriceList`: a wholesale price from a cost and a markup, with quantity tiers by markup (`fromMarkup()`) or by
  unit price (`from()`), added in ascending order and never raising the unit price; `basePrice()`, `unitPriceFor()`,
  `totalFor()` and `tierFor()`, each `WholesaleTier` with its quantity, unit price and markup.
- `PricingException`, implemented by `InvalidRate`, `InvalidPricing`, `UndefinedRate` and `AmountOutOfRange`.
- Unit and property tests, a test of every example of the suggested and wholesale prices documentation, and phpbench
  benchmarks of summaries, cascades and ended prices.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
