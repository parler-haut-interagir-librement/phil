# phil/commerce-consignment-bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/commerce-consignment-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/commerce-consignment-bundle)

**Stock you own and stock left on consignment, side by side on one SKU: who owns each unit, which leaves first, the
margin or commission of every sale, and what each consignor is owed, paid and given back.**

phil/commerce-consignment-bundle is a Symfony bundle, a module of `phil/commerce-bundle` on the stock of
`phil/commerce-logistics-bundle`. A shop that receives goods from distributors, authors and artisans buys some of them
for resale and sells others on consignment, and one supplier may do both on the same product: five copies of a book
bought, five more left to be sold for a share. The logistics module counts the units on hand; this bundle keeps, in a
ledger of its own, **who owns them**.

Each receipt line opens a **layer**: units of one SKU, at one location, owned by the shop at a unit cost, or by a
consignor on agreed terms — a fixed share of each unit sold, or the shop's commission as a rate. Sales, transfers,
returns and counts take units from layers and put them back, and every movement is an entry of the ledger. When both
kinds are on hand, a configurable **issue order** says which units leave first: the shop's own, the consigned ones, or
the oldest receipt. Each sale records what it brought in and its **margin** — the price less the cost for the shop's
units, the commission — the price less the consignor's share — for consigned ones, and what the shop owes each
consignor.

Each consignor has an **agreement** — how long their units stay, what happens to the unsold ones, how often they
receive a statement —, versioned so that a change never rewrites the past. Numbered **statements**, read from the
ledger and frozen once issued, tell each consignor what sold, what came back and what is still in the shop; the shop
writes the **payouts** it made, partial or whole, against the balance owed — the bundle moves no money. Unsold units
are listed when due, then **given back** to their consignor or **bought** by the shop at the agreed cost.

## Status

| Part | State |
|---|---|
| Stock split by owner in layers per SKU, location and owner; the ledger of every movement | Implemented and tested |
| Receipts whose lines are bought, at a unit cost, or consigned, with a share in `Money` or a commission in parts per million | Implemented and tested |
| Issue order between owned and consigned units: owned first, consigned first, oldest first; per SKU | Implemented and tested |
| Sales, returns to the layers they left, transfers between locations, counts of every owner or of one | Implemented and tested |
| Margin of the shop's units and commission of consigned ones, through the `MarginCalculator` port | Implemented and tested |
| Shipped orders of `phil/commerce-bundle` written to the ledger, once, when the logistics module announces each shipment; `RecordShipment` for a worker | Implemented and tested |
| Customer returns, count differences and units given back moved by the logistics module's own movements; receipts checked against the owner and unit cost of each purchase order line | Implemented and tested |
| Doctrine tables on SQLite, MySQL 8.4 and PostgreSQL 17, keys compared byte by byte, instants in UTC; a repository in memory | Implemented and tested |
| Agreements with each consignor — term, unsold units, statement frequency —, versioned without rewriting the past | Implemented and tested |
| Statements per consignor and period, read from the ledger, numbered and frozen; CSV and a replaceable Twig template | Implemented and tested |
| Payouts written, partial or whole, refused twice by an idempotency key, cancelled by reversal; the balance and its state | Implemented and tested |
| Unsold units due, given back off the ledger and the shelf, or bought by the shop at the agreed cost | Implemented and tested |
| Screens | None: the bundle offers services; an application draws its own screens |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundles to register and the tables. |
| [Configuration](doc/configuration.md) | The issue order, for every SKU and per SKU. |
| [The ownership ledger](doc/ledger.md) | Layers, entries, the issue order, sales, returns, transfers and counts. |
| [Receiving goods](doc/receiving.md) | Receipt lines bought or consigned, purchase orders, stock already on hand. |
| [Margins and commissions](doc/margins.md) | What a sale brought in, cost or owes, and how a commission rate splits a price. |
| [Agreements](doc/agreements.md) | The term, what happens to the unsold units and the statement frequency of each consignor, version by version. |
| [Statements](doc/statements.md) | Periods, what a statement holds, numbers, CSV and the template. |
| [Payouts](doc/payouts.md) | What was paid, the same payout twice, reversals and the balance. |
| [Unsold units](doc/unsold-units.md) | The units due, return slips and buy-outs. |
| [With the logistics module](doc/logistics.md) | How the stock moves, receipts against purchase orders, and how shipped orders reach the ledger. |
| [Scope](doc/scope.md) | What the bundle does, and what it leaves to later versions or to the application. |
| [Extension points](doc/extension-points.md) | The services to replace and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, and the servers its integration tests read. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle::class => ['all' => true],
Phil\CommerceConsignmentBundle\PhilCommerceConsignmentBundle::class => ['all' => true],
```

```php
use Phil\CommerceConsignmentBundle\Application\ConsignmentStock;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\Money\Currency;
use Phil\Money\Money;

$cad = Currency::of('CAD');

// Five copies bought at 12.00, five left by their author for 15.00 each copy sold.
$stock->receive(Receipt::of('DN-1042', 'store', [
    ReceiptLine::purchased('BOOK-123', 5, Money::of(1200, $cad)),
    ReceiptLine::consigned('BOOK-123', 5, 'author-17', ConsignmentTerms::share(Money::of(1500, $cad))),
]), $clock->now());

$sale = $stock->sell('2026-0001', 'BOOK-123', 'store', 6, Money::of(2000, $cad), $clock->now());
$sale->margin();            // revenue, cost or share, margin
$sale->owedToConsignors();  // ['author-17' => 15.00 × the consigned copies sold]
$entityManager->flush();
```

At the end of the month, the author's statement, its payout, and the copies that did not sell:

```php
$author = Owner::consignor('author-17');
$statement = $statements->issue($author, $cad, $statements->lastPeriod($author, $clock->now()), $clock->now());
$payouts->record($transferId, $author, $statement->share(), $clock->now(), 'transfer', null, $clock->now());
$unsold->giveBack('RS-1', $author, 'store', ['BOOK-123' => 2], $clock->now());
$entityManager->flush();
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later, with `phil/commerce-bundle` 0.4 and `phil/commerce-logistics-bundle` 0.1.1.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
