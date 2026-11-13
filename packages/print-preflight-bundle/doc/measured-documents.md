# Measured documents

A page laid out for print in a browser — a print stylesheet, a paged-media polyfill, a headless browser — gives what
the preflight needs to a script running on the rendered page: for each page box its size; for each element
`getBoundingClientRect()` relative to the page box; for each image `naturalWidth` and `naturalHeight`. The script, a
Stimulus controller of the application for example, sends those numbers as JSON; `MeasuredDocument` reads them into
the model of `phil/print-preflight`.

```json
{
    "unit": "px",
    "pages": [
        {
            "width": 559.37, "height": 793.7, "bleed": 11.34,
            "images": [
                {"id": "cover", "x": -11.34, "y": -11.34, "width": 582.05, "height": 816.38, "naturalWidth": 1800, "naturalHeight": 2500, "bleeds": true},
                {"id": "portrait", "x": 40, "y": 120, "width": 160, "height": 160, "naturalWidth": 600, "naturalHeight": 800, "scaledWidth": 160, "scaledHeight": 213.33, "colourSpace": "rgb"}
            ],
            "boxes": [
                {"id": "title", "x": 40, "y": 40, "width": 479, "height": 48},
                {"id": "band", "x": -11.34, "y": 700, "width": 582.05, "height": 40, "colourSpace": "cmyk", "bleeds": true, "inkCoverage": 280, "transparency": true},
                {"id": "rule", "x": 40, "y": 100, "width": 479, "height": 0.5, "colourSpace": "spot", "overprint": true, "lineWidth": 0.5}
            ]
        }
    ],
    "fonts": [
        {"name": "Example Sans Bold", "fsType": 0, "subset": true},
        {"family": "Example Serif", "weight": 400, "style": "italic", "subset": true}
    ]
}
```

| Field | Of | Required | Notes |
|---|---|---|---|
| `unit` | document | no | `px` (default), `pt`, `mm` or `in`: the unit of every length |
| `pages` | document | yes | In order |
| `fonts` | document | no | The fonts the document prints with |
| `width`, `height` | page | yes | The trim size |
| `bleed` | page | no | What the page carries beyond the trim box on every side; 0 by default |
| `images`, `boxes` | page | no | The elements placed |
| `id` | image, box | yes | What a finding names: an element id, a block id |
| `x`, `y`, `width`, `height` | image, box | yes | The frame, from the top left corner of the trim box, y downwards |
| `naturalWidth`, `naturalHeight` | image | yes | The pixel size |
| `scaledWidth`, `scaledHeight` | image | both or neither | The size the whole image is scaled to when its frame crops it (`object-fit: cover`) |
| `colourSpace` | image, box | no | `gray`, `rgb`, `cmyk` or `spot`; RGB for an image, unknown for a box, by default |
| `bleeds` | image, box | no | Whether the element is meant to run off the page; `false` by default |
| `inkCoverage` | image, box | no | The total area coverage at the element's darkest point, in percent (`320` for 320 %); unknown when left out or `null` |
| `transparency` | image, box | no | Whether the element is drawn with transparency (opacity, a blend mode, an alpha channel); `false` by default |
| `overprint` | box | no | Whether the element prints over what lies beneath instead of knocking it out; `false` by default |
| `lineWidth` | box | no | The width of the thinnest stroke the element draws, in `unit`; unknown when left out or `null` |
| `name`, `fsType` | font | `name` | A font known by name and the `fsType` of its `OS/2` table; 0 by default |
| `family`, `weight`, `style` | font | `family` | A font found by the `FontResolver`; 400 and `normal` by default |
| `embedded`, `subset` | font | no | `true` and `false` by default; a browser printing to PDF embeds web fonts as subsets |

`fromJson(string $json)` and `fromArray(array $data)` give the `Document`. Anything they cannot read throws
`InvalidMeasurement`, whose message names the field — `The "naturalWidth" of an image must be a number.` —, and the
library's refusals — a negative bleed, an image of no pixel, a negative ink coverage or line width — come out the same
way.

The four last element fields are what a browser cannot always tell: a script that does not know an element's ink
coverage or thinnest stroke leaves the field out, and the ink coverage and hairline checks pass over that element
rather than guess. A script that knows them — from the colours of a print stylesheet, from the `border-width` of a
rule — gives them, and those checks and the overprint and transparency checks report on the element.

A font named by family needs a `FontResolver`: with `phil/fonts-bundle` enabled, the catalogue of its font directory
answers, so a stylesheet's `font-family` is checked against the very file the site serves. Without one, such a font
throws `InvalidMeasurement`; name it with `name` and `fsType` instead.

A measured document carries identifiers and numbers, never the text of the pages: an endpoint that receives it from a
browser stores nothing personal, and a message that queues a check carries the identifier of the stored document.
