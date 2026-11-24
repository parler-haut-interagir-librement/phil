# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `ReturnRequest`, `ReturnLine`, `ReturnLabel`, the enums | Every rule, each step and the steps refused, questions, expiry, labels, slips numbered once, anonymization; the value, the fee and its rounding, missing units, the specials' charges never below zero, the delivery; every refusal |
| `Returns`, `Settlement`, `ReturnPolicy` | Line values with promotions and taxes, quantities left and freed, the window from the placement and from the last shipment, the rate limit per customer, restocking, refunds to several payments, shortfalls, store credit, exchanges for less and for more, questions, expiry, labels, the events of each step |
| `ReturnValuation`, `DeliveryRefund` | A special broken by one product, by both, pair by pair, the tax it saved, its charge capped; a set refused in part; the delivery under each policy, once per order, freed, waived |
| `ReturnCreditNotes`, `ReturnCreditNote` | Partial lines with their tax, fees, specials, delivery, missing units; one note per return; lines credited whole by the core, before and under the lock; returns not received, orders without a receipt |
| `ReturnSlips`, `ReturnDocumentTypes`, `PagedMediaReturnSlips` | A slip numbered once in its series, its lines and label; only an authorized return; a PDF in the locale asked, on more pages for many lines, in English without a translator |
| `CatalogSetParts`, `PromotionSpecials`, `LogisticsShipmentDates` | Composite products as sets, bundle prices as specials, the last shipment of an order |
| `LogisticsRestocking` | Units back where they left from, reservation by reservation, or at the configured location, a configured line's product; another sellable, an untracked SKU and units beyond what left putting nothing back; an unknown return refused |
| `ReturnsGateway` | The method `exchange`, its refunds |
| `StoredValueStoreCredit` | Credit through the stored-value module |
| Mapping | Every column of the tables |
| `PhilCommerceReturnsBundle` | The configuration and its bounds, the policy, the services with DoctrineBundle, store credit, sets, specials and shipment dates with their modules, the logistics restocking off by default, its location bounded and its refusal without the logistics, slips with or without a renderer, nothing without Doctrine, the mapping, the refusal without the core |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on an in-memory SQLite database, the core, the
stored-value, catalogue, promotions and logistics modules — the last with its costing, first in, first out — and this one: `ReturnFlowTest` takes a return through to store credit,
refuses a part of a catalogue's set, takes a promotion's special back, gives the delivery back, numbers a slip and a
credit note in their series and reads them back, expires a return found in SQL, tells each step to a listener, and puts the resellable units of a return back in the
logistics module's stock at the cost they left at, not at the day's. Installed on its own,
where FrameworkBundle is a development requirement, the suite is skipped.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/commerce-returns-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/commerce-returns-bundle/tests/Benchmark
```
