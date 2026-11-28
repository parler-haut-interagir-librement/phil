# phil/product-bundles

**Sell products as one bundle: fixed or discounted price, stock set by the scarcest part, cost composed from its
parts, whole returns, bundles shared by sellers.**

phil/product-bundles is a plain PHP library. It defines what a bundle holds, prices it, says how many can be sold and
what one costs, freezes a sale on its order line, and returns it whole.

**A bundle holds no stock of its own.** A `ProductBundle` is a SKU of its own over one to 50 components, each with the
units one bundle holds. Its stock is the smallest of its components' available units divided by those units: eleven
decks and twenty rule books make five bundles of two decks and one book, and the decks set the limit. Selling draws
each component's units; returning puts them back.

**Two prices.** A bundle has a fixed price of its own, or the price of its components less a rate in parts per
million, rounded by a named `\RoundingMode`. Either way the price is shared among the components by what they sell
for apart, the shares adding up to the price to the unit.

**Two ways to be taxed.** An assembled bundle — a book with its dice — is sold as one product, taxed on its whole price
by its own category. A collection — a print book and its PDF, every title of a creator — is taxed component by
component, each at its own rate on its share. Both are sold on one line and returned whole, never a component alone.
A rebate between two products sold on lines of their own is a promotion, not a bundle.

**A composed cost.** What one bundle costs is the sum of its components' unit costs times their units, as the stock
valuation says now — weighted average or first in, first out, as the shop chose. One unknown cost leaves the cost
unknown, never too low. The margin follows, with its rate over the price.

**A frozen line.** `SoldBundle` keeps what the sale was: the unit price, each component's share and unit cost, the
quantity sold and returned. It is stored with the order line and read back, so a return refunds what was paid and puts
back what was drawn, whatever the bundle has become since.

**Bundles shared by sellers.** A `CooperativeBundle` gathers the products of several sellers: a host invites them, each
with a relative weight, and every participant approves before it is sold. The first approval locks the participants
and their weights; a charity may take a share of every sale first. The library reckons each participant's part;
paying it is the application's work.

The library reads only through ports — the bundles, the components' prices, stock and costs — and keeps no state
between calls, so its services are safe in a long-running worker.

## Status

| Part | State |
|---|---|
| Bundles, their components and price policies; the stored row read back | Implemented, tested and fuzzed |
| The quote, the shares of the price, the savings | Implemented and tested |
| The stock set by the scarcest component | Implemented and tested |
| The composed cost and the margin | Implemented and tested |
| The frozen sale, its draws, whole returns; the stored line read back | Implemented, tested and fuzzed |
| Cooperative bundles: approvals, the lock, the charity's share, the shares of a revenue | Implemented, tested and fuzzed |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and what the application provides. |
| [Bundles](doc/bundles.md) | `ProductBundle`, its components, its kind, and how it is stored. |
| [Price, stock and cost](doc/price-stock-cost.md) | The price policies, the shares, the stock, the composed cost and the margin. |
| [Sales and returns](doc/sales-and-returns.md) | The frozen line, the draws, whole returns and the taxes of each kind. |
| [Cooperative bundles](doc/cooperative.md) | Participants, weights, approvals, the lock, the charity and the shares. |
| [Security](doc/security.md) | The limits, what is stored, and what a stored row must be. |
| [Extension points](doc/extension-points.md) | The ports an application implements, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests, fuzzing and benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/product-bundles
```

```php
use Phil\Money\Currency;
use Phil\Pricing\Rate;
use Phil\ProductBundles\BundleAvailability;
use Phil\ProductBundles\BundleComponent;
use Phil\ProductBundles\BundleKind;
use Phil\ProductBundles\BundlePricing;
use Phil\ProductBundles\Bundles;
use Phil\ProductBundles\ComposedCost;
use Phil\ProductBundles\PricePolicy;
use Phil\ProductBundles\ProductBundle;
use Phil\ProductBundles\SoldBundle;

$bundleRepository->save(new ProductBundle(
    'STARTER', BundleKind::Assembled, Currency::of('CAD'),
    [new BundleComponent('RULES'), new BundleComponent('DICE', 2), new BundleComponent('SCREEN')],
    PricePolicy::discountOnComponents(Rate::ofPercent('15')),
));

$bundles = new Bundles($bundleRepository, new BundlePricing($prices), new BundleAvailability($stock), new ComposedCost($costs));

$bundles->quote('STARTER')->price;      // the components' prices less 15 %
$bundles->stock('STARTER')->quantity;   // limited by the scarcest component
$bundles->margin('STARTER')?->rate;     // null while a component's cost is unknown

$sold = $bundles->sell('STARTER', 2);   // frozen: store $sold->toArray() with the order line
foreach ($sold->draws() as $draw) {
    $logistics->issue($draw->sku, $draw->units);
}

$refund = SoldBundle::fromArray($line)->refund(1);   // one whole bundle back: $refund->amount, $refund->restock
```

## Requirements

PHP 8.5 or later, phil/money 0.1 and phil/pricing 0.1.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
