# Price rules

A product is sold at its price, or by one of two rules: the buyer chooses the price, or other buyers fund copies
for people who cannot pay. A discount for a buyer who already owns a related product is a promotion of
`phil/commerce-promotions-bundle`, through its condition on the products owned.

## Pay what you want

```php
$zine->payWhatYouWant(suggested: Money::of(800, Currency::of('CAD')), maximum: Money::of(5_000, Currency::of('CAD')));

$modifier->add(
    $order,
    PriceRuleSellables::reference($zine),                                  // pay_what_you_want:ZINE
    1,
    $context,
    PriceRuleSellables::configuration(Money::of(1_200, Currency::of('CAD'))), // the option price: 1200
);                                                                         // a line at 12.00
```

The buyer's price is an option of the line, the core's amount option (`OptionKind::Amount`). `PriceRuleSellables`
answers the reference `pay_what_you_want:<sku>` for the core — the product's name in the buyer's locale, a unit price
of zero, the product's tax category — and declares, as the core's `SellableOptions`, one required option, `price`,
whose `AmountRange` runs from the base price up to the maximum. The core checks the value, describes it on the line
(`12.00 CAD`), and charges it as the option's supplement, taxed with the line in the product's tax category. Two
prices are two lines, their configurations differing; the same price again adds to its line.

| Member | Rule |
|---|---|
| `Product::payWhatYouWant(?suggested, ?maximum)` | The suggested price is not below the base price, and the maximum not below the suggested price, nor the base price; both in the product's currency |
| `Product::fixPrice()` | Sells the product at its price again |
| `Product::isPayWhatYouWant()`, `suggestedPrice()`, `maximumPrice()` | Read the rule |
| `Product::chosenPrices(?defaultMaximum)` | The `AmountRange` a buyer chooses in, from the base price up to the product's maximum or the default maximum; `null` for a fixed price, without a maximum, or with a default maximum in another currency or below the suggested price — the base price when none is suggested |
| `Product::reprice()` | The base price stays below the suggested price and the maximum |
| `PriceRuleSellables::reference(product)` | `pay_what_you_want:<sku>` |
| `PriceRuleSellables::configuration(price)` | The line's configuration: the option `price`, in minor units written in digits |
| `PriceRuleSellables::chosenPriceOf(line)` | What one unit of a line of a chosen price costs before its promotions; `null` for another line |

- **The maximum is required.** A product without one of its own takes the site's default,
  `pay_what_you_want.default_maximum` ([Configuration](configuration.md)); without either, the product is not sold
  at a chosen price — the core finds nothing for its reference —, so a buyer never reaches the largest integer.
- **The value is minor units**, digits without a sign, a decimal point or a leading zero. A price below the base price,
  above the maximum, not written as an amount, or left unset is refused with the core's `InvalidConfiguration`,
  naming the option `price`, before the order changes.
- **The option's label** is `Your price`, the key `phil_commerce_catalog.chosen_price`; a page shows the suggested
  price first, read from `suggestedPrice()`.
- **Refused**: a product that does not let the buyer choose, a withdrawn product and a family are never sold. A line
  whose product stops letting the buyer choose, or loses its maximum, is never left at zero: reading its options again
  — the core does at each recalculation — throws `CatalogRefused` until the line is removed. An order in another
  currency than the product's is refused the same way.
- The channels that sell the product sell its chosen prices, through `ProductAvailability`. The price books price the
  type `product` only: a chosen price is the price.

## Community copies

```php
$zine->offerCommunityCopies(Money::of(500, Currency::of('CAD')));

$modifier->add($order, SellableReference::of('community_copy', 'ZINE'), 2, $context);   // funds two copies

$entityManager->wrapInTransaction(fn () => $copies->claim($zine, $member->id(), $clock->now()));
```

A community copy is funded by other buyers and claimed by someone who cannot pay. `CommunityCopies` listens to the
core's `OrderPurchased` and reads the order paid in full; before its promotions and taxes:

- each `community_copy:<sku>` line gives what it costs, the copy price times its units;
- each `pay_what_you_want:<sku>` line gives what its buyer paid above the suggested price, or above the base price
  when none is suggested, times its units — the price chosen being its option `price`.

| Member | Rule |
|---|---|
| `Product::offerCommunityCopies(?copyPrice)` | More than zero, in the product's currency; `null` offers none and keeps what was funded and claimed |
| `CommunityCopies::available(product)` | One copy per copy price funded, less the copies claimed; never below zero |
| `CommunityCopies::funded(product)` | What funded its copies; `null` when it offers none |
| `CommunityCopies::claim(product, claimant, at)` | One copy per claimant and product, while one is left; refused with `CatalogRefused` otherwise |
| `CommunityCopies::fund(order, at)` | Records what a paid order gave; the SKUs it funded |

- **An order funds once.** Its funding is one row per product and order: the event delivered twice, or a worker
  retried, adds nothing.
- **A claim runs inside a transaction.** It locks the product's row first, so two claims of the last copy run one
  after the other, and the second is refused.
- **The claimant is a stable identifier**, 1 to 64 visible ASCII characters — never a name or an address. The
  application delivers the copy: a download, a copy set aside at the counter.
- **An order in another currency than the product's funds nothing.**

The line of a community copy is described as `Community copy of <name>`, the key
`phil_commerce_catalog.community_copy` of the domain `phil_commerce_catalog`, which the module translates in English
and French; an application overrides it in its own translations.

## Storage

With DoctrineBundle, the rules are columns of `phil_commerce_catalog_product` — see [Products](products.md) —, and
community copies two tables.

| Table | Primary key | Columns |
|---|---|---|
| `phil_commerce_catalog_community_copy_funding` | `sku`, `order_number`, `VARCHAR(64)` each | `amount` `BIGINT`, in the product's minor units; `funded_at` `DATETIME`, in UTC |
| `phil_commerce_catalog_community_copy_claim` | `sku`, `claimant`, `VARCHAR(64)` each | `claimed_at` `DATETIME`, in UTC |

`DoctrineCommunityCopyLedger` sums the funding and counts the claims in SQL, and locks the product with
`PESSIMISTIC_WRITE`.
