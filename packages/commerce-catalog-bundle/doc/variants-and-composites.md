# Variants and composites

## Families and variants

A **family** names the axes its variants differ by — a T-shirt by size and colour —, in the order a buyer chooses
them. It is chosen through its variants and never sold itself: the core finds nothing for its reference. A
**variant** is a product of its own, with its SKU, price, channels and stock, that gives each axis of its family one
value.

```php
use Phil\CommerceCatalogBundle\Application\ProductVariants;

$shirt = Product::create('TEE01', ProductKind::Physical, 'T-shirt', Money::of(2_500, Currency::of('CAD')), 'goods');
$shirt->defineAxes(['size', 'colour']);
$products->add($shirt);

$variants->create($shirt, 'TEE01-M-BLACK', ['size' => 'M', 'colour' => 'black']);           // "T-shirt — M / black", 25.00
$variants->create($shirt, 'TEE01-XXL-BLACK', ['size' => 'XXL', 'colour' => 'black'], Money::of(2_800, Currency::of('CAD')));

$variants->find($shirt, ['colour' => 'black', 'size' => 'M']);   // the variant, whatever order the values come in
$variants->choices($shirt);                                      // ['size' => ['M', 'XXL'], 'colour' => ['black']]
```

| Rule | |
|---|---|
| Axes | Up to 5, each up to 64 lowercase letters, digits or `_`, starting with a letter; a variant or a composite names none |
| Values | One per axis, 1 to 64 characters, not blank; no other option |
| A variant takes | Its family's kind, tax category and channels; its name and price too, unless given — the name is the family's followed by its values |
| `ProductVariants::create()` | Refuses a SKU already taken and a second variant of the family with the same values, withdrawn ones included |
| `ProductVariants::choices()` | The values of the variants still sold, axis by axis, each once, in the order of the variants' SKUs |

### What a variant takes from its family

| What | How |
|---|---|
| Attributes | `ProductVariants::attributesOf($variant)`: the family's, with the variant's own over them; what a page and `ProductSchema` show |
| Tax category, channels, serial numbers | Copied when the variant is created; `ProductVariants::cascade($family)` copies them again to every variant and returns the variants changed |
| Name, price, options | The variant's own once created |

`ProductVariants::priceRange($family)` gives the lowest and the highest base price of the variants still sold, or
`null` when none is, for a family shown with a range of prices.

A variant is stored as any product, with its family's SKU in `family` and its values in `options`.
`ProductFamilies::variantsOf()`, which the Doctrine adapter implements beside `ProductRepository`, lists a family's
variants by SKU. A catalogue generated from templates — a T-shirt design crossed with its sizes and colours — creates
its variants through `ProductVariants`.

## Composite products

A **composite** product is made of others, each with a number of units — a starter box of a rulebook and two dice
sets. It is sold as one product, on one line, at its own price and in its own tax category: the whole of it is
taxed together, never part by part.

```php
use Phil\CommerceCatalogBundle\Application\CompositeProducts;
use Phil\CommerceCatalogBundle\Domain\ValueObject\ProductComponent;

$composites->compose($box, [ProductComponent::of('RULEBOOK'), ProductComponent::of('DICE-SET', 2)]);
$composites->partsOf(SellableReference::of('product', 'BOX'));   // the parts one unit of a line holds
```

| Rule | |
|---|---|
| Parts | Up to 50, each a product of the catalogue named once, never the composite itself, 1 to 1 000 units |
| Depth | One level: a part is neither a family nor a composite |
| A family | Is never a composite |
| No parts | `compose($product, [])` makes it a product of its own again |

The parts are what the stock gives out and what a return takes back whole: `partsOf()` reads them from an order
line's reference, and finds none for a product of its own or another sellable. A special between two products sold
apart — two lines, each with its share of the discount — is a bundle of `phil/commerce-promotions-bundle` instead.
