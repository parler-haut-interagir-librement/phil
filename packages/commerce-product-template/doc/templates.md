# Templates

A template is a blank something is printed on, described as data. A shirt template has a size axis, a colour axis,
perhaps a version axis; a poster template a format and a paper. Nothing in the library knows the difference.

## Labels and swatches

A `Label` holds a text per locale — `fr`, `en`, `fr_CA` —, at least one, each 1 to 255 characters. `in('fr_CA')` reads
the text of that locale, else of its language, `fr`, else the first text given; `in()` reads the first.

A `Swatch` is what a selector shows for a value: `Swatch::colour('#1A1A1A')`, six or eight hexadecimal digits kept in
uppercase, or `Swatch::photo('swatches/rainbow.jpg')` for a value no single colour shows — a rainbow, a metallic
finish.

## Axes and values

```php
$colour = Axis::of('colour', Label::of(['en' => 'Colour', 'fr' => 'Couleur']),
    AxisValue::of('BK', Label::of(['en' => 'Black', 'fr' => 'Noir']), Swatch::colour('#1A1A1A'), 'black',
        ['front' => 'blanks/unisex/front-bk.jpg', 'back' => 'blanks/unisex/back-bk.jpg']),
    AxisValue::of('GL', Label::of(['en' => 'Glow', 'fr' => 'Phosphorescent']), Swatch::photo('swatches/glow.jpg')),
);
```

| Part | Rule |
|---|---|
| Axis code | Up to 32 lowercase letters, digits or `_`, starting with a letter; never `design`, `lang` nor `template` |
| Values | 1 to 500, in the order a selector lists them, each code once |
| Value code | 1 to 16 letters, digits, `.`, `_` or `-`, starting with a letter or digit: what the SKU writes |
| Supplier name | What the supplier of this template's blank calls the value — `sport grey` —, 1 to 255 characters |
| Photos | The photo of the blank in this value, by face code, a path relative to the store of images |
| Channels | The channels that sell the products of this value; every channel when empty |

The supplier's name belongs to the value of a template, not to a shared colour: two blanks from two suppliers name
the same colour differently. A template made for a convention booth gives its convention version the channel of the
booth; the plan then sells the products of that version through that channel only.

Paths are relative to wherever the application keeps its images: segments of letters, digits, `.`, `_` and `-`,
never `.` nor `..`, never absolute, at most 512 characters.

## Faces and print zones

```php
$front = Face::of('front', Label::of(['en' => 'Front']),
    PrintZone::of('chest', 50, 60, 300, 400, 300, PhotoBox::of(312, 260, 400, 533)),
    PrintZone::of('pocket', 200, 60, 80, 80, 600),
);
```

A `PrintZone` is a rectangle in millimetres from the top left corner of its face — position and size, each at most
10 000 mm —, the resolution of its print file, from 72 to 2400 dpi, 300 by default, and the `PhotoBox` it covers on
the photos of its face, in pixels, for mockups. The photos of one face share their framing whatever the value they
show, so the box is given once. A face has one zone at least; its first zone is where an artwork naming no zone
goes.

## Prices

```php
$pricing = Pricing::of($cad(2_500))
    ->withPrice('size', '2X', $cad(2_800))
    ->withPrice('size', '3X', $cad(3_000))
    ->withSupplement('colour', 'GL', $cad(300))
    ->withSupplement('version', 'L', $cad(1_000));
```

A combination's price is the price of its value on the pricing axis, else the base, plus the supplement of each of
its values. Prices are on one axis only; supplements on any. Every amount is in the base's currency and never
negative. A discount is a promotion of the catalogue, not a negative supplement.

## Exclusions

```php
Exclusion::of(['colour' => ['PK', 'LB'], 'size' => ['4X', '5X']]);
```

An exclusion removes every combination that has, on each axis it names, one of the values it lists: pink and light
blue in the two largest sizes, whatever the version. A template lists as many as the supplier's range needs; the
plan never makes the excluded combinations, and a storefront never offers them.

## The template

```php
$shirt = Template::of('unisex', Label::of(['en' => 'Unisex shirt']), SkuPattern::parse('{design}{lang}{version}{size}{colour}'),
    $pricing, [$version, $size, $colour], [$front, $back], $exclusions, supplier: 'Brand 64000');
```

| Check | Refused with `InvalidDefinition` |
|---|---|
| Code | Not 1 to 32 letters, digits, `.`, `_` or `-`, starting with a letter or digit |
| Axes | None, more than 8, one twice, or more than 10 000 combinations before exclusions |
| Faces | One twice, or a value with a photo of a face the template lacks |
| SKU pattern | A placeholder that is neither an axis nor `design`, `lang` or `template` |
| Pricing, exclusions | A value or axis the template lacks |

`combinations()` yields every combination it makes, the last axis varying fastest; `makes()` tells whether one is
excluded.
