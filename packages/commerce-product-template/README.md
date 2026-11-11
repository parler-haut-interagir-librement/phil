# phil/commerce-product-template

**One template and one design give every product of a catalogue: each SKU, its price, its channels and its
artworks.**

phil/commerce-product-template describes a product made to order on a blank. A **template** is the blank: its axes —
a size, a colour, a version — with their values in order, each with its code, its label in several languages, its
swatch, the name the supplier gives it and the photo of the blank on each face; the price of each combination, from a
base, a price per value of one axis and supplements on any axis; the faces and their print zones in millimetres; the
combinations the supplier does not make; and the pattern of the SKU, `{design}{lang}{version}{size}{colour}` or any
other. A **design** is what is printed: its code, its language versions, its artworks per face and zone, the templates
it is made on and the values it allows. The **planner** turns a design on a template into one product per language
and combination, refuses a pattern that would give two products the same SKU, and gives the same plan for the same
input, so a catalogue regenerated from it is updated rather than duplicated.

It is for a shop that sells printed goods in many sizes and colours from a handful of blanks: shirts, hoodies, mugs,
posters. A shirt is the example the documentation follows; nothing in the library knows what a shirt is.

It is a plain PHP library with `phil/money` for its prices and nothing else. The Symfony
bundle `phil/commerce-product-template-bundle` stores templates and designs, generates the products of a catalogue
from the plan, composes their mockups and declares their options.

## Status

| Part | State |
|---|---|
| Axes and values: codes, labels by locale, swatches, supplier names, photos per face, channels | Implemented and tested |
| Prices: base, a price per value of one axis, supplements on any axis | Implemented and tested |
| Faces and print zones in millimetres, resolution, rectangle on the photos | Implemented and tested |
| Combinations excluded by axis values | Implemented and tested |
| SKU patterns, glued or separated, with the collision check | Implemented and tested |
| Designs: language versions, artworks by face, zone, language and condition, templates and values allowed | Implemented and tested |
| Planning: products, prices, channels, artworks, names and attributes | Implemented and tested |
| Arrays for storage, versioned and checked on reading | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Templates](doc/templates.md) | `Template`, `Axis`, `AxisValue`, `Label`, `Swatch`, `Face`, `PrintZone`, `PhotoBox`, `Pricing`, `Exclusion`. |
| [SKU patterns](doc/sku-patterns.md) | Placeholders, literals, the collision check. |
| [Designs](doc/designs.md) | `Design`, `LanguageVersion`, `Artwork`: languages, artworks and what a design allows. |
| [Planning](doc/planning.md) | `VariantPlanner`, `Variant`, `PlacedArtwork`: what a plan holds and refuses. |
| [Storage](doc/storage.md) | `TemplateArray` and `DesignArray`: the arrays, their version, what reading refuses. |
| [Extension points](doc/extension-points.md) | What an application chooses. |
| [Exceptions](doc/exceptions.md) | `ProductTemplateException`, `InvalidDefinition`, `PlanRefused`. |
| [Testing](doc/testing.md) | The library's tests, fuzz targets and benchmark. |
| [Reference](doc/reference.md) | Every public type of the library. |

## Quick start

```sh
composer require phil/commerce-product-template
```

```php
use Phil\CommerceProductTemplate\{Artwork, Axis, AxisValue, Design, Exclusion, Face, Label, LanguageVersion};
use Phil\CommerceProductTemplate\{Pricing, PrintZone, SkuPattern, Swatch, Template, VariantPlanner};
use Phil\Money\{Currency, Money};

$cad = static fn (int $cents): Money => Money::of($cents, Currency::of('CAD'));

$shirt = Template::of('unisex', Label::of(['en' => 'Unisex shirt', 'fr' => 'T-shirt unisexe']),
    SkuPattern::parse('{design}{lang}{version}{size}{colour}'),
    Pricing::of($cad(2_500))->withPrice('size', '2X', $cad(2_800))->withSupplement('version', 'C', $cad(1_000)),
    [
        Axis::of('version', Label::of(['en' => 'Version']),
            AxisValue::of('O', Label::of(['en' => 'Regular'])),
            AxisValue::of('C', Label::of(['en' => 'Convention']), channels: ['convention'])),
        Axis::of('size', Label::of(['en' => 'Size']),
            AxisValue::of('M', Label::of(['en' => 'M'])), AxisValue::of('2X', Label::of(['en' => '2XL']))),
        Axis::of('colour', Label::of(['en' => 'Colour']),
            AxisValue::of('BK', Label::of(['en' => 'Black']), Swatch::colour('#1A1A1A'), 'black', ['front' => 'blanks/front-bk.jpg']),
            AxisValue::of('PK', Label::of(['en' => 'Pink']), Swatch::colour('#F4A7C0'), 'azalea')),
    ],
    [Face::of('front', Label::of(['en' => 'Front']), PrintZone::of('chest', 50, 60, 300, 400))],
    [Exclusion::of(['colour' => ['PK'], 'size' => ['2X']])],
);

$gnome = Design::of('NAQ001', Label::of(['fr' => 'Le gnome', 'en' => 'The gnome']),
    [LanguageVersion::of('F', 'fr'), LanguageVersion::of('E', 'en')],
    [Artwork::of('front', 'art/naq001-front.png'), Artwork::of('front', 'art/naq001-front-en.png', language: 'E')]);

foreach (new VariantPlanner()->plan($shirt, $gnome) as $variant) {
    echo $variant->sku, ' ', $variant->price->toDecimal(), ' ', $variant->name('en'), "\n";
}
// NAQ001FOMBK 25.00 Le gnome – T-shirt unisexe (Regular, M, Black)
// ...
```

## Requirements

PHP 8.5 or later with `ext-mbstring`, and `phil/money`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
