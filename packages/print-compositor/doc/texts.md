# Texts

## Fonts

A `TypeFace` is a font file texts are drawn in:

```php
use Phil\PrintCompositor\TypeFace;

$face = TypeFace::fromFile('/path/to/ExampleSerif.ttf');
$face->font->family();                    // read by phil/fonts
$face->missingCharacters('Grüße, €5');    // the characters the font does not cover, each once
```

The file is read through `phil/fonts`, whose `Font` the face keeps: its names, its embedding permissions, its
character map. FreeType, through GD, draws from a TrueType or OpenType file: a WOFF, a WOFF2 or a collection is refused
with `UnsupportedFont`, as is a file that cannot be read or is not a font. The path is resolved when the face is read,
so the face draws from the same file wherever the process runs.

A print file is an image: the font is not embedded in it. Whether a font's licence allows drawing it in images that
are sold is the application's to check, from the licence `phil/fonts` reads.

## Text items

A `TextItem` is a text, its face and its box, with:

| Argument | Default | Meaning |
|---|---|---|
| `colour` | opaque black | The colour of the text, its alpha included |
| `maxSize`, `minSize` | 72 and 6 | The sizes, in points, the text may take |
| `horizontal`, `vertical` | `Centre`, `Middle` | Where the text sits in its box |
| `wrap` | `true` | Whether the text breaks at spaces to fit the box's width |
| `lineSpacing` | 1.2 | The distance between two baselines, in ems |

The text breaks at its line breaks either way. A blank text, a minimum size that is not positive, a maximum below the
minimum and a line spacing that is not positive are refused with `InvalidLayout`.

## Fitting

`TextFitter::fit()` finds the size a text is drawn at, at a resolution, 300 dpi by default:

1. A text with a character its font does not cover is refused with `MissingGlyphs`, which names each character and
   its code point: drawn, it would print as an empty box or not at all.
2. The sizes are tried from the maximum down, by steps of half a point, then the minimum itself when the steps miss
   it. At each size the text is wrapped at spaces into lines no wider than the box, a word wider than the box alone on
   its line.
3. The first size whose ink fits the box, across and down, is kept. The ink is measured on the pixels FreeType draws,
   not on its bounding box, which can be a few pixels wider or narrower than the ink depending on the font: a text
   that is said to fit never draws outside its box. The bounding box only saves measuring the sizes that are clearly
   too large: more than `TextFitter::ESTIMATE_MARGIN` times the box.
4. A text that does not fit even at its minimum is refused with `TextDoesNotFit`.

```php
use Phil\PrintCompositor\TextFitter;

$fitted = new TextFitter()->fit($item);
$fitted->size;          // 31.5
$fitted->lineTexts();   // ['Here lies a brave', 'adventurer']
```

A customer's text is checked this way when it is entered, with the same item the print file will draw, so the text
refused is refused before the order and the text accepted is drawn as it was shown. `PrintCompositor` calls the fitter
again when it draws, and gets the same answer.

## Alignment

The text is placed by its ink, not by the advance of its glyphs: a left-aligned text touches the left edge of its box,
a right-aligned one the right edge, a text aligned at the top has its highest ink on the top edge. Each line is aligned
across on its own; the lines are aligned down as one block, from the highest ink of any line to the lowest.
