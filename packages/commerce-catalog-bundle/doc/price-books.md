# Price books

A price book gives unit prices other than the base prices, for whom and when it applies: a customer group, a
channel — a site, a point of sale —, a period, and quantity tiers per SKU.

```php
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Domain\ValueObject\PriceTier;

$blackFriday = PriceBook::create('black-friday', 'Black Friday', Currency::of('CAD'), channel: 'web',
    startsAt: new DateTimeImmutable('2026-11-27 00:00 America/Toronto'), endsAt: new DateTimeImmutable('2026-12-01 00:00 America/Toronto'));

$wholesale = PriceBook::create('wholesale', 'Wholesale', Currency::of('CAD'), customerGroup: 'wholesale', after: ['black-friday']);
$wholesale->setPrice(PriceTier::of('DICE-D20', 1, Money::of(399, Currency::of('CAD'))));
$wholesale->setPrice(PriceTier::of('DICE-D20', 50, Money::of(349, Currency::of('CAD'))));
```

## The order of the books

A book names, in its `after` list, the books it is tried after: their prices win over its own wherever both apply.
`PriceBookOrder::sort()` orders the books by code, then moves each one after the books its list names, with
`BeforeAfterSorter`, the sorter Symfony orders tagged services with. No number ranks a book: adding one between two
others is naming them, not renumbering the rest.

| Case | Order |
|---|---|
| No list | By code, in byte order |
| `wholesale` after `black-friday` | `black-friday` is tried first, wherever their codes fall |
| A name that is no active book | Passed over: a withdrawn or deleted book leaves the others where their other names put them |
| Lists that loop — `a` after `b`, `b` after `a` | Set aside: the books are tried by code |

## Which price a line takes

`PriceBookPrices` answers the core for the references of type `product`, with the line's whole quantity:

1. the books not withdrawn, in the order of their `after` lists;
2. those in the product's currency that apply: the buyer's customer group and the channel — both from the core's
   `PricingContext` —, and the moment, the start included and the end excluded; a condition left empty applies to
   everyone, everywhere, always;
3. the first that prices the SKU for the quantity wins, with the tier of the highest quantity reached;
4. no book: the product's base price.

| Member of `PriceBook` | Rule |
|---|---|
| `code` | Up to 64 lowercase letters, digits, `_`, `.` or `-` |
| `name`, `currency` | A name; the currency of its prices |
| `after()`, `placeAfter(list)` | The books it is tried after: up to 1 000 codes, never its own; repeated names count once |
| `customerGroup`, `channel` | `null`, or a name |
| `startsAt`, `endsAt` | `null`, or the period; the end comes after the start |
| `setPrice(PriceTier)`, `removePrice(sku, minQuantity)` | One price per SKU and quantity, in the book's currency |
| `tiers()`, `priceFor(sku, quantity)`, `appliesTo(group, channel, at)` | Read the book |
| `withdraw()`, `restore()` | A withdrawn book applies to nothing |

`PriceTier::of(sku, minQuantity, unitPrice)`: a SKU, from 1 unit, at zero or more.

The tiers are kept sorted by SKU in byte order (`10` before `9`, digits before letters), then by quantity, which is
the order `tiers()` lists them in. `setPrice()` finds a tier's place by binary search, so setting each tier of a large
book costs a search, not a sort of the whole book.

## Reading the books

`PriceBookPrices` reads and orders the active books once, with their tiers indexed by SKU, and keeps them until
`reset()`: a cart re-priced on each change asks the repository once, not once per line. The module tags the service
`kernel.reset`, so FrameworkBundle resets it between the requests and the messages of a long-running worker. A book
created or changed in the middle of a request prices from the next one.

## Storage

With DoctrineBundle, a price book is one row of `phil_commerce_catalog_price_book`, its tiers in a JSON column.

| Column | Type | Holds |
|---|---|---|
| `code` | `VARCHAR(64)`, primary key | The code |
| `name` | `VARCHAR(255)` | The name |
| `after_books` | JSON | The codes of the books it is tried after |
| `currency` | `CHAR(3)` | The currency |
| `customer_group`, `channel` | `VARCHAR(64)`, nullable | The conditions |
| `starts_at`, `ends_at` | `DATETIME` of type `datetime_utc_immutable`, nullable | The period, written and read back in UTC |
| `tiers` | JSON | SKU, minimum quantity, unit price in minor units |
| `active` | `BOOLEAN`, indexed by `phil_commerce_catalog_price_book_active` | `false` once withdrawn |

The repository reads the active books by code on that index; `PriceBookOrder` orders them.

## Moving books ordered by number

A table whose books are ordered by a numeric `priority` column moves to `after` lists with
`PriceBookOrderMigration`, from an application's Doctrine migration:

```php
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\PriceBookOrderMigration;

final class Version20261112000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        PriceBookOrderMigration::run($this->connection);
    }
}
```

It keeps the order the books were tried in. Each book is placed after every book of a higher priority, in the order
they were tried, the 1 000 nearest at most; books of one priority stay in the order of their codes. Withdrawn books
are migrated too, so that restoring one puts it back in its place.

| Step | What changes |
|---|---|
| 1 | The column `after_books` is added, nullable |
| 2 | Each row gets its list |
| 3 | `after_books` becomes required; `priority` and every index that holds it are dropped; the index on `active` is added unless one exists |

`run()` returns `false`, and changes nothing, on a table already without `priority`. The table then matches the
mapping, so `doctrine:migrations:diff` finds nothing left to change. Tested on SQLite, MySQL 8 and PostgreSQL 17.
