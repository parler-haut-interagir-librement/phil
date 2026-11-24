# Target prices

`PriceCalculator` gives the price that reaches a target from a cost:

| Method | Formula | Example |
|---|---|---|
| `priceForMargin($cost, Rate $margin)` | cost / (1 − margin) | 6.00 at 40 % → 10.00 |
| `priceForMarkup($cost, Rate $markup)` | cost × (1 + markup) | 6.00 at 50 % → 9.00 |
| `priceForCostShare($cost, Rate $share)` | cost / share | 3.00 at 30 % → 10.00 |

The cost share is the target margin said the other way, as a kitchen says its food cost: a cost share of 30 % is a
margin of 70 %.

## Rounding up

The prices round away from zero by default. On a positive cost, that is up: the price is the smallest that reaches the
target, so the margin or the markup it gives is never below it. 6.00 at a 35 % margin is 9.2308 exactly; the price is
9.24, whose margin is 35.06 %, where 9.23 would give 34.99 %.

```php
$prices = new PriceCalculator();

$prices->priceForMargin($cost, Rate::ofPercent('35'))->toDecimal();                                 // "9.24"
$prices->priceForMargin($cost, Rate::ofPercent('35'), RoundingMode::HalfAwayFromZero)->toDecimal(); // "9.23"
```

On a negated cost, the price is the negation of the sale's: a refund priced this way reverses the sale exactly.

## What is refused

| Target | Refused | Exception |
|---|---|---|
| Margin | 100 % or more: no price leaves room for the cost | `InvalidRate` |
| Markup | below -100 %: the price would have the other sign; -100 % is a zero price | `InvalidRate` |
| Cost share | zero or less | `InvalidRate` |
| Any | a cost whose product by one million, or by the markup, leaves the native integer range | `AmountOutOfRange` |

A negative target margin or markup is accepted: it is a price below cost, a clearance for instance. The price keeps
the currency of the cost.

A computed price usually goes through a [price ending](price-endings.md) before it reaches a shelf.
