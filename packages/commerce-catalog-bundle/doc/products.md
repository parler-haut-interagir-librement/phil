# Products

A product is named by its SKU and sold to the core under the reference type `product`: `product:DICE-D20` is one
unit at its base price — or at a price book's — in its tax category.

```php
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\Money\Currency;
use Phil\Money\Money;

$dice = Product::create('DICE-D20', ProductKind::Physical, 'Dé à 20 faces', Money::of(499, Currency::of('CAD')), 'goods');
$dice->describe('colour', 'rouge');
$products->add($dice); // ProductRepository; the application commits its unit of work
```

| Member | Rule |
|---|---|
| `sku` | Up to 64 letters, digits, `.`, `_` or `-`, starting with a letter or digit |
| `kind` | `Physical` (shipped; the logistics module counts its stock), `Digital`, `Service` |
| `name()`, `rename()` | 1 to 255 characters, not blank: the description of the order line |
| `price`, `reprice()` | Zero or more, in the product's currency: the base price |
| `taxCategory()`, `classify()` | 1 to 64 characters: the category the core's tax rates read |
| `attributes()`, `attribute()`, `describe()` | Names of up to 64 lowercase letters, digits or `_`; values of 1 to 255 characters; `null` removes one |
| `channels()`, `sellThrough(list)`, `isSoldThrough(channel)` | The channels that sell it; every channel when empty; an order without a channel only gets what every channel sells |
| `offer()`, `changeOffer(Offer)`, `offerThrough(channel)` | What the channels that sell it offer of it: listed, orderable and priced by default; hidden where it is withdrawn or outside the selection; a variant starts with its family's |
| `withdraw()`, `restore()` | A withdrawn product is not sold: the core finds nothing for its reference |
| `defineAxes()`, `axes()`, `isFamily()` | A family, chosen through its variants and never sold itself — see [Variants and composites](variants-and-composites.md) |
| `family()`, `options()` | The family of a variant, and its value on each axis |
| `composeOf()`, `components()`, `isComposite()` | A composite product and its parts |
| `trackSerialNumbers(bool)`, `tracksSerialNumbers()` | Each unit sold carries a serial number; physical products only; a variant takes its family's |
| `payWhatYouWant()`, `fixPrice()`, `offerCommunityCopies()` | The price rules — see [Price rules](price-rules.md) |
| `suggestRetailPrice(?Money)`, `suggestedRetailPrice()` | The retail price its maker or distributor suggests, more than zero, in the product's currency; `null` forgets it. It never prices a line |

**The suggested retail price** is the list price a sign or a product page compares the shop's own price with; it
is kept beside the base price and read back, and neither the core nor the price books price a line with it. A variant
has its own, never its family's.

**Serial numbers** are kept with the stock of the logistics module, which registers each unit's number; the
catalogue says which products carry one, so that the application asks for it at a sale or a reception.

## Offers

A product is offered through the core's `Offer`: a `Visibility`, then whether the cart takes it and whether its price
is shown.

| Offer | Its page | The lists and search | Its price | The cart |
|---|---|---|---|---|
| `Offer::full()`, the default | Shown | Listed | Shown | Takes it |
| `Offer::of(Visibility::Unlisted)` | Shown by its direct link: a convention edition, a presale | Absent | Shown | Takes it |
| `Offer::of(Visibility::Listed, orderable: false)` | Shown: sold out for good | Listed | Shown | Refuses it |
| `Offer::of(Visibility::Listed, orderable: false, priceShown: false)` | Shown: an announcement | Listed | Hidden | Refuses it |
| `Offer::hidden()` | Absent | Absent | Hidden | Refuses it |

```php
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;

$dice->changeOffer(Offer::of(Visibility::Listed, orderable: false)); // its page stays, without a button to buy it
$preview->changeOffer(Offer::of(Visibility::Unlisted));             // sold to whoever has the link
```

In a channel, the offer is the product's own, unless the product is withdrawn or outside the channel's selection —
then it is hidden there. A variant is offered at the stricter of its own offer and its family's: a hidden family hides
its variants, a family left to its direct link leaves them there, a family not orderable makes them not orderable. A
new variant starts with its family's offer.

`ProductAvailability` answers the core's `SellableAvailability` with that offer, for the product, its chosen price
— `pay_what_you_want:<sku>` — and its community copies. The pages read it through the core's
`SellableOffers::of($reference, $channel)` — or `ProductAvailability::of($product, $channel)` for a product they
already hold —: they list a product `isListed()`,
show the page of one `isReachable()`, its price when `priceShown`, the button that buys it when `orderable`, and
answer a hidden one as not found. The cart refuses what is not `orderable` with `NotSoldThere`, through
`OrderModifier`.

## Lists of products

`ProductListing` lists the products a channel lists — never one left to its direct link —, page by page, for a catalogue
page or a feed of any length. `DbalProductListing` reads them on DBAL, without entities, by SKU: the cursor of a page
names where the next starts.

```php
$page = $listing->page('web', after: null, limit: 50, locale: 'fr');

foreach ($page->products as $product) { // ListedProduct
    // $product->sku, ->name, ->price, ->offer, ->value('colour')
}

$next = $listing->page('web', after: $page->next, limit: 50, locale: 'fr'); // none once $page->next is null
```

| Argument | Rule |
|---|---|
| `channel` | The channel whose selection is listed; `null` lists what every channel sells |
| `after` | The cursor of the page before, `ProductPage::$next` |
| `limit` | 1 to 500 products |
| `locale` | The names in that locale, along the chain of `translations.locales`; each product's own name otherwise |
| `families` | Whether families are listed beside their variants; `true` by default |
| `direction` | `\SortDirection::Ascending` by SKU by default, or `Descending` |

A product is listed when it is active, in the channel's selection, and neither it nor its family is hidden; it comes
with the stricter of the two levels. Active products that are not hidden are read in batches of the page's size and
the selection, kept as JSON, is checked on each row; a page reads ten batches at most, so a channel that sells few of
many products may get a short page, whose cursor still reads on. The names of a page come in one query.

A product is withdrawn rather than deleted, so that the orders and receipts that name it keep their meaning.

## Storage

With DoctrineBundle, a product is one row of `phil_commerce_catalog_product`.

| Column | Type | Holds |
|---|---|---|
| `sku` | `VARCHAR(64)`, primary key | The SKU |
| `kind` | `VARCHAR(16)` | `physical`, `digital` or `service` |
| `name` | `VARCHAR(255)` | The name |
| `price`, `currency` | `BIGINT`, `CHAR(3)` | The base price, in minor units |
| `tax_category` | `VARCHAR(64)` | The tax category |
| `attributes` | JSON | Attribute → value |
| `channels` | JSON | The channels that sell it |
| `visibility` | `VARCHAR(16)`, default `listed` | `listed`, `unlisted` or `hidden` |
| `orderable`, `price_shown` | `BOOLEAN`, default `true` | Whether the cart takes it, whether its price is shown |
| `active` | `BOOLEAN`, indexed | `false` once withdrawn |
| `family` | `VARCHAR(64)`, nullable, indexed | The SKU of a variant's family |
| `axes` | JSON | A family's axes, in order |
| `options` | JSON | A variant's axis → value |
| `components` | JSON | A composite's parts: SKU and units |
| `serial_numbers` | `BOOLEAN` | Whether each unit carries a serial number |
| `pay_what_you_want` | `BOOLEAN` | Whether the buyer chooses the price |
| `suggested_price`, `maximum_price` | `BIGINT`, nullable | The price shown first and the highest one, in minor units; without a maximum, the configuration's `pay_what_you_want.default_maximum` bounds the price |
| `community_copy_price` | `BIGINT`, nullable | The price of a community copy, in minor units |
| `suggested_retail_price` | `BIGINT`, nullable | The suggested retail price, in minor units, in the product's currency |

A table created for 0.1.0-alpha gains the column `suggested_retail_price`, null for the existing rows, and nothing
else changes: generate the migration with Doctrine's `doctrine:migrations:diff`, or read it first with
`doctrine:schema:update --dump-sql`.

A product's name in other locales is one row of `phil_commerce_catalog_product_translation` per locale: see
[Names and classification](names-and-classification.md).

## schema.org

```php
$data = ProductSchema::of($product, $productUrl, $priceForTheVisitor, $nameInThePagesLocale, $variants->attributesOf($product));
// all optional but the product
```

A `Product` — name, SKU, page, each attribute given (its own by default) and each option of a variant as a
`PropertyValue`, the family of a
variant as `inProductGroupWithID` — with its `Offer`: the price given (from a price
book or after a promotion), else the base price, as `price` and `priceCurrency` from `phil/money`, and
`availability` `InStock`, or `Discontinued` once the product is withdrawn. The application prints it as JSON-LD
with `JSON_HEX_TAG`.
