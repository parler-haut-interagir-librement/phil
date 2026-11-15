# Testing

## How the bundle is tested

The unit tests live in `tests/Unit`. None boots a kernel, opens a connection or reads the clock: every time is
passed in.

| Area | What is covered |
|---|---|
| `Order`, `OrderLine` | Lines merged by reference and kept in order, quantities and prices refused below their bounds, currencies, adjustments replaced by type and refused without change, neutral adjustments left out of totals, a removed line's adjustments removed |
| `Order` states | Placing, cancelling, the moves refused, a placed or cancelled order that no longer changes, an empty cart that cannot be placed |
| `Order` mapping | The table, identifier, version, index and every column, as an application's migration creates them |
| `OrderLinesType`, `AdjustmentsType` | JSON written and read back, Unicode and slashes unescaped, every malformed or refused stored value turned into a DBAL conversion error with its cause |
| `Channel`, `Channels` | Every rule of a channel and its hostnames, the channel of a host, the orders it opens, its table |
| `Visibility`, `Offer`, `SellableOffers` | The three levels and the stricter of two, an orderable offer refused without its price, a hidden offer never orderable nor priced, the strictest of every selection, the asking stopped at the first hidden answer |
| `OptionDefinition`, `AmountRange`, `LineOptions` | Each kind declared, checked and described; an amount within its range, written as minor units, refused outside it or written otherwise, charged and taxed on its line, another amount another line; the fuzz target `chosen-amount` on amounts nobody wrote |
| `Payment` | Creation, methods refused, every move each event allows and every one it refuses, a notification applied once and a refused one not recorded, refunds adding up, what a payment holds by state; its mapping |
| `OrderPayments`, `PaymentNotification`, `PaymentStart` | Split payments reserving what is in flight, refunds and disputes lost returned, the payable never negative, the five states; the refusals of each value object |
| `Payments` | Starting all or part of what is left, amounts and currencies refused, carts and unknown methods refused, notifications applied once, refused or for an unknown payment, refunds asked and recorded only on confirmation |
| `PaymentWebhookController` | 204, 400 and 404 for each outcome, a commit only for an acknowledged notification, headers handed over as lists of strings |
| `Receipt`, `Issuer` | A copy of the placed order kept as issued, taxes summed by code, full and partial credit notes, the order's adjustments with the last line, every refusal; its mapping and that of the sequences |
| `Receipts` | One receipt per order, numbered in its series; credit notes in theirs; no number taken by a refused document |
| `DocumentType`, `DocumentRegister`, `CoreDocumentTypes` | Bounds of a type, its numbers, a code or prefix declared twice, the series asked inside the transaction, each type's visibility and the types a buyer's list shows, hidden by default |
| `SellableReference`, `Sellable`, `PricingContext`, `Adjustment` | Bounds of types, identifiers and labels, refusals with their messages |
| `OrderModifier` | The resolver of the type answers alone, prices asked in order for the whole line, every processor after each change, unknown sellables, a line no order of the channel may hold refused, one its direct link alone reaches sold |
| `TaxOrderProcessor`, `ConfiguredTaxRates` | Each line by each rate of its category, after its own promotions only; no zone, no tax; idempotent; percentages read and refused; taxes taken out of prices that include them, neutral |
| `PaymentAttemptKey` | Order and IP keys never collide; IPv6 spellings and addresses of one /64 share a key; prefix lengths on and across byte boundaries, from 1 to 128, clear exactly the bits past them; IPv4-mapped addresses; refusals |
| `PaymentAttemptPolicy`, `PaymentAttemptState`, `PaymentAttemptDecision` | Boundaries of the window rule, the three fields of a state, expiry, `Retry-After` rounding, most restrictive decision |
| `PaymentAttemptLimiter` | Budget, penalties accumulating into one wait, the cap, expiry starting from an empty state, attempts during a wait not recorded, time-to-live |
| `Quote`, `Quotes`, `OrderAmendments` | A quote recomputed, frozen and placed at its prices, declined back to a cart, expired; an order amended until its receipt, what was paid beyond the amended total |
| `DeferredSale`, `DeferredSales`, `Deposit`, `DeferredSalePolicy` | A layaway from the order to its balance, a deposit paid before the sale opens, one sale per order, the due balances called earliest first in bounded batches, a cancelled sale releasing its stock before its listeners hear it |
| `OrderWorkflow`, `OrderLifecycle`, `OrderStateChange` | Each transition and the moves refused, the journal, forced moves refused by default and announced by `OrderStateForced`, statuses cleared by each move, `OrderCancelled` once per cancellation — cancelled, expired or forced — and never for a forced move out of `cancelled` |
| `GiftOptions`, `GiftWrapping`, `ManualDiscount`, `ManualDiscountProcessor` | One wrapping per wrapped order, the recycled wrap, a store that does not wrap; discounts on a line or the whole order before the taxes, never below zero, shared by what is left of each line |
| `Payments` announcing, offline payments | `OrderPurchased` once per order paid in full, at the application's clock, never for a refused notification; offline payments declared then confirmed, never abandoned as stale, refunded without a gateway |
| `WishList`, `WishLists`, `PaymentLink`, `PaymentLinks` | Tokens shown once and kept hashed, a list bought from and counted on `OrderPurchased`, links that open their order until they expire, are revoked, or the order is paid |
| `OrderChange`, `OrderChanges`, `OrderExpiry`, `Refund`, `Refunds` | Changes prepared, confirmed, versioned and refused on an older revision or after a receipt; the sweep by channel, bounded, with or without the workflow; refunds granted then executed, never beyond what is left, `OrderRefunded` once per refund executed, with the order's channel and customer or without them when the order is not found |
| `UnknownShipments` | No order nor line known to have left |
| `ReceiptPdf` | A receipt and a credit note printed, long lines and long receipts over several pages, options below their line, embedded fonts, PDF/A |
| `OrderWorkflowPass`, `DoctrineDocumentNumbering`, `DoctrineDeferredSaleRepository` | The workflow added to the registry when there is one; the series and the deferred sales' queries |
| `PhilCommerceBundle` | Alias `phil_commerce`, the four tags read by `OrderModifier` and the gateways' tag, autoconfiguration, the tax processor and its resolver, a refused rate, dashed categories, zones and codes kept as written, receipt prefixes too long for the number column or shared by the two series |

A functional suite under `tests/Functional/` boots a real kernel, `tests/Functional/App/TestKernel.php` (FrameworkBundle
and the bundle with GST, QST and HST under `phil_commerce.tax_rates`), with its cache in a directory of its own under
the system temporary directory. `BundleRegistrationTest` pins the alias, the reference `config:dump-reference
phil_commerce` prints, and the build failures a refused rate and an unknown key cause; `ApplicationWiringTest` plugs a
sellable resolver and a price list in by autoconfiguration, as a module would, builds and taxes an order through
`OrderModifier`, and spends a limiter budget with an in-memory store wired from parameters, as
[installation.md](installation.md) shows. `OrderStorageTest` places an order and reads it back whole from a fresh unit
of work, on DoctrineBundle and an in-memory SQLite database, and sees a change written with its version moved on.
`PaymentFlowTest` pays a placed order through a gateway the application plugs in, delivers the processor's signed
notification to the webhook controller, and reads the payment and the order back paid. `ReceiptFlowTest` issues receipts
and a credit note inside transactions, reads them back as issued, sees a rolled-back receipt give its number back, and a
number refused outside a transaction. `SalesLifeStorageTest` stores a gift placed through the workflow and journalled,
links, changes, refunds and the register, and sweeps unpaid orders by their channel; `DeferredSaleStorageTest` stores a
layaway and calls its balance when due; `SalesConfigurationTest` builds with the defaults and refuses what the bundle
cannot use. Installed on its own, the functional suite is skipped: Symfony wires no event dispatcher when
FrameworkBundle is a development requirement.

Neither suite needs a connection or an environment variable: the storage tests run on an in-memory SQLite database,
and `vendor/bin/phpunit packages/commerce-bundle/tests/Functional` runs the functional suite alone. To run them on a
server, `PHIL_COMMERCE_TEST_DATABASE_URL` names it; each storage test drops the bundle's tables and creates them again:

```bash
eval "$(vendor/bin/phil-dev services env)"   # after vendor/bin/phil-dev services up
PHIL_COMMERCE_TEST_DATABASE_URL="$PHIL_TEST_MYSQL_URL" vendor/bin/phpunit packages/commerce-bundle/tests/Functional
PHIL_COMMERCE_TEST_DATABASE_URL="$PHIL_TEST_POSTGRESQL_URL" vendor/bin/phpunit packages/commerce-bundle/tests/Functional
```

The mapping is kept portable across SQLite, MySQL 8.4 and PostgreSQL 17: the `lines` columns are quoted, since
`LINES` is reserved in MySQL, and no JSON column declares a default, which MySQL refuses.

## Running the tests

### From the root of the monorepo that incubates the bundle

The tests run on the root `vendor/`:

```bash
vendor/bin/phpunit packages/commerce-bundle/tests
```

`composer packages` installs the bundle alone in a fresh directory, with `phil/ddd-bundle`, `phil/money`, `phil/tax`
and `phil/paged-media` from their repository, checks its declared dependencies with `composer-dependency-analyser` against
`composer-dependency-analyser.php`, then runs its tests.

### Installed alone

```bash
cd packages/commerce-bundle
composer install
vendor/bin/phpunit --no-configuration tests
```

`phil/ddd-bundle`, `phil/money`, `phil/tax` and `phil/paged-media` come from Packagist, or from a `path` repository
when they are worked on beside the bundle.

## Testing an application built on the bundle

### Orders and taxes

Build orders through `OrderModifier` with your own resolvers and processors, in a unit test, and assert on minor
units, line by line; the rounding and the breakdowns are tested in `phil/tax`, whose testing guide gives the cases
to keep:

```php
$modifier = new OrderModifier([new GiftCards($denominations)], [], [new TaxOrderProcessor(new ConfiguredTaxRates([
    'goods' => ['QC' => ['GST' => '5', 'QST' => '9.975']],
]))]);

$order = Order::open('1', Currency::of('CAD'));
$modifier->add($order, SellableReference::of('gift_card', '25'), 1, PricingContext::of(1, $now));
$modifier->changeTaxZone($order, 'QC');

self::assertSame(2_500, $order->total()->minorUnits); // gift cards are exempt
```

Keep a test with a promotion on a line, to see the tax computed on the discounted line, and one that runs every
processor twice, to see that nothing is added twice.

### The limiter

The limiter takes the time as an argument: drive it with fixed `DateTimeImmutable` values, or a mock clock, and an
in-memory store:

```php
final class InMemoryPaymentAttemptStateStore implements PaymentAttemptStateStore
{
    /** @var array<string, array{PaymentAttemptState, int}> */
    public array $saved = [];

    public function find(PaymentAttemptKey $key): ?PaymentAttemptState
    {
        return $this->saved[$key->value][0] ?? null;
    }

    public function save(PaymentAttemptKey $key, PaymentAttemptState $state, int $timeToLiveSeconds): void
    {
        $this->saved[$key->value] = [$state, $timeToLiveSeconds];
    }
}
```

Assert that the time-to-live passed to `save()` is the policy's window: that is what bounds how long a client
address is kept.

### The store adapter

Test against the real backend that a saved state disappears after its time-to-live, and that it reads back to an
equal state (`attempts`, `cumulativeWaitSeconds`, `lastAttemptAt` with its microseconds).

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `OrderModifier::add()` and `Order::total()` on carts of 5, 20
and 100 lines, and the hydration of the lines column by `OrderLinesType`. From the monorepo root:

```bash
composer bench -- packages/commerce-bundle/tests/Benchmark
```
