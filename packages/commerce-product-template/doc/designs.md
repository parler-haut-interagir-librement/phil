# Designs

A design is what is printed on a template, independently of the blank: its code, the first part of its SKUs, its
name, the languages it exists in, its artworks, the templates it is made on and the values it allows.

```php
$gnome = Design::of('NAQ001', Label::of(['fr' => 'Le gnome', 'en' => 'The gnome']),
    [LanguageVersion::of('F', 'fr'), LanguageVersion::of('E', 'en')],
    [
        Artwork::of('front', 'art/naq001/front-fr.png', language: 'F'),
        Artwork::of('front', 'art/naq001/front-en.png', language: 'E'),
        Artwork::of('front', 'art/naq001/front-convention.png', when: ['version' => ['C']]),
        Artwork::of('back', 'art/naq001/back.png', 'full'),
    ],
    templates: ['unisex', 'fitted'],
    allowed: ['colour' => ['BK', 'NV', 'CH'], 'version' => ['O', 'C']],
);
```

## Language versions

A `LanguageVersion` is the code the SKU writes for the language, 1 to 8 characters, and the locale the products of
that version are named in. A design has up to 8; one with none is planned once, its `{lang}` empty.

## Artworks

An `Artwork` is an image printed in a zone of a face: the colour print file of that zone, at its proportions.

| Part | Rule |
|---|---|
| Face | The code of a face of the template; on a template without that face, the artwork is left out |
| Zone | A zone of that face; its first zone when `null`; a zone the face lacks refuses the plan |
| Language | The code of one of the design's language versions; every version when `null` |
| Condition | Value codes by axis: the combinations it applies to; every combination when empty |

On each zone of each product, the **most specific** artwork that applies is printed. An artwork of one language is
more specific than any artwork of every language; then the more axes its condition names, the more specific it is.
Two artworks that apply equally refuse the plan with `PlanRefused::ambiguousArtwork()`; two artworks with the same
face, zone, language and condition refuse the design with `InvalidDefinition`. A design has up to 200 artworks.

## What a design allows

`templates` lists the templates it is made on, every template when empty; planning it on another is refused with
`PlanRefused::templateNotAllowed()`. `allowed` lists, by axis, the values the design is made in — the colours it
looks right on, the versions it has —; an axis it does not name allows every value, and so does a template that lacks
the axis.
