# Generation

## Storing a template and a design

A template and a design are built with `phil/commerce-product-template` and stored. A template also says what the
catalogue makes of its products — their kind and their tax category:

```php
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;

$templates->add(ProductTemplate::create($unisexShirt, ProductKind::Physical, 'goods'));
$designs->add(ProductDesign::create($gnome));
$entityManager->flush();
```

`redefine()` replaces the definition under the same code, `classify()` the kind and tax category; the products
change at the next generation.

## Generating

```php
$report = $generation->generate('NAQ001', 'unisex');
$entityManager->flush();
```

`CatalogGeneration::generate()` plans the products of the design on the template, then, for each planned SKU:

| The catalogue | The generation |
|---|---|
| Has no product of that SKU | Creates it: kind and tax category of the template, name, price, attributes, channels |
| Has the product, as a member of this group | Renames, reprices, classifies, describes it again and sells it through its channels |
| Has the product withdrawn | Does the same and restores it |

Then it **withdraws** every member of the group the plan no longer has, with its product: a colour the design stopped
allowing, a language version dropped. Nothing is ever deleted, so the orders and receipts that name a product keep
their meaning; generating again once the colour is back restores its products.

The **name** is the design's, the template's and the label of each value, in the locale of the language version, or
the configured `default_locale`: `Le gnome – T-shirt unisexe (Ordinaire, M, Noir)`. The **attributes** are `design`,
`template`, `language` when the design has versions, and the code of each value by axis — `size: M`, `colour: BK`;
an attribute a product no longer has is removed. The **channels** are those the plan gives: the convention version of
a shirt is sold through the channel of the convention booth only.

The generation does not flush: the application flushes its unit of work once it returns, and runs it in a
transaction when it wants all or nothing.

## The report

`GenerationReport` lists the SKUs `created`, `updated`, `restored`, `unchanged` and `withdrawn`. Running a generation
twice in a row gives a report whose `changedNothing()` is true: generating is idempotent.

## Withdrawing a group

`CatalogGeneration::withdraw('NAQ001', 'unisex')` withdraws the group, its members and their products; its report
lists the members that were still active. Generating the group again restores it.

## What is refused

`TemplateRefused`, before anything changes:

| Factory | When |
|---|---|
| `unknownTemplate()`, `unknownDesign()`, `unknownGroup()` | No such template, design or group |
| `plan()` | The library refuses the plan: SKUs in collision, a template the design is not made on, an artwork that cannot be placed |
| `foreignSku()` | A planned SKU belongs to another group, or to a product of the catalogue outside any group |
| `kind()` | The template's kind changed: a product never changes its kind |
| `currency()` | The template's currency changed: a product never changes its currency |

A refused generation leaves the catalogue and the groups as they were.

## Running it

Generation reads and writes one row per product: for a template of thousands of combinations, run it in a worker —
a console command or a message handler of the application, its message carrying the design and template codes only.
