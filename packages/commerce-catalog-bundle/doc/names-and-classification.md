# Names and classification

## Translated names

A product's name is written in its own text; its translations live on `ProductTranslation`, one row per product and
locale, through the translations of `phil/doctrine-behaviours`. With the locales declared in the
[configuration](configuration.md), `ProductTexts` reads them.

```php
use Phil\CommerceCatalogBundle\Application\ProductTexts;

$texts->translate($dice, 'fr')->rename('Jeu de dés');   // the stored row, or a new empty one, persisted for the next flush
$names = $texts->of($products);                          // one query for the list, in the request's locale
$names->name($dice);                                     // "Jeu de dés", or the product's own name
$texts->locales($dice);                                  // ['fr'], for the alternates of its page
```

A translation is served whole, from the first locale of the fallback chain that has one; a field is never copied from
another locale, and a translation whose name is not written yet falls back to the product's own name. An order line
takes its description in the request's locale, through `ProductSellables`.

| Column of `phil_commerce_catalog_product_translation` | Type | Holds |
|---|---|---|
| `id` | `INTEGER`, primary key | The row |
| `product`, `locale` | `VARCHAR(64)` with a foreign key, `VARCHAR(12)`; unique together | The product, deleted with it, and the locale |
| `name` | `VARCHAR(255)` | The name in that locale; empty until written |

## Categories and tags

With `phil/taxonomy-bundle` installed and its taxonomy registered, `ProductClassification` files products in its tree
of categories and its tags, shared with the rest of the site, renamed and merged in one place. A product is the
resource `phil_commerce_product`, by its SKU.

```php
use Phil\CommerceCatalogBundle\Application\Contract\ProductClassification;

$classification->categorize($dice, ['games', 'dice']);   // by category keys; an unknown key is refused
$classification->tag($dice, ['Gift ideas', 'Résine']);   // the names of the tags it now has
$classification->inCategory('games');                    // SKUs filed in the category and its whole branch
$classification->forget($dice);                          // every tag and category taken away
```

Without the taxonomy, the service and its alias are removed from the container.
