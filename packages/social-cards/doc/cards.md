# Cards

A card is the 1200×630 image a page is shared with when it has none of its own. It is drawn from a template and a
content, deterministically, and kept under a fingerprint of everything it shows.

## Templates

```php
$template = CardTemplate::fromArray([
    'title_font' => '/fonts/Inter-Bold.ttf',
    'subtitle_font' => '/fonts/Inter-Regular.ttf',
    'background' => '#0f172a',
    'title_color' => '#f8fafc',
    'subtitle_color' => '#cbd5e1',
    'accent' => '#e11d48',
    'background_image' => '/images/texture.png',
    'logo' => '/images/logo.png',
    'padding' => 80,
    'title_max_size' => 64,
    'title_min_size' => 36,
    'title_max_lines' => 3,
    'subtitle_size' => 32,
    'subtitle_max_lines' => 2,
]);
```

| Key | Default | Bounds |
|---|---|---|
| `title_font` | required | A TrueType or OpenType file of 32 MiB at most |
| `subtitle_font` | the title's | The same |
| `background`, `title_color`, `subtitle_color` | `#ffffff`, `#111827`, `#4b5563` | `#rgb`, `#rgba`, `#rrggbb` or `#rrggbbaa` |
| `accent` | none | A colour, drawn as a band of 12 pixels along the bottom edge |
| `background_image` | none | A PNG, JPEG, GIF or WebP of 10 MiB, 8,000 pixels a side and 16,777,216 pixels in all at most — its size read in its header, before GD decodes it —, scaled to cover the card |
| `logo` | none | The same, in the bottom-left corner, shrunk in its proportions to 360×96 pixels at most |
| `padding` | 80 | 0 to 200 pixels |
| `title_max_size`, `title_min_size` | 64, 36 | 8 to 200 pixels, the smallest not above the largest |
| `title_max_lines` | 3 | 1 to 6 |
| `subtitle_size`, `subtitle_max_lines` | 32, 2 | 8 to 120 pixels; 1 to 4 lines |

`CardFont` reads each font with phil/fonts and keeps its family, its licence and its SHA-256; WOFF, WOFF2 and
collections are refused, since GD draws from a plain `sfnt` file. A raster card does not embed the font, but the
licence a font is used under is the application's to respect: `$font->licence->spdx()` names the common ones.

`CardTemplate::fingerprint()` hashes the fonts' and images' SHA-256 rather than their paths, with the colours, sizes
and bounds: the same template deployed elsewhere has the same fingerprint.

## The title

`TextFitter` fits a text to a box. It cuts the text where a line may break — `LineBreaker` applies the Unicode line
breaking algorithm of ICU in the text's locale: between the ideographs of Chinese and Japanese, at the spaces of other
scripts, never before `:`, `;`, `!` or `?`, never inside `« »`, never at a no-break space — and wraps the pieces at
each size. The largest size at which the text holds in its lines is found by halving the range of sizes, so a title of
300 characters costs a few hundred measures. A word wider than the box is cut between two characters. When the text
does not hold even at the smallest size, its first lines are kept and the last one ends with an ellipsis.

The subtitle is drawn at its own size below the title, in as many lines as fit above the logo or the bottom margin:
fewer lines, or none.

## Drawing

`GdCardRenderer` draws the background colour, the background image cropped around its centre, the title at the
top-left within the margin, the subtitle, the logo and the accent band, and writes an opaque PNG at the highest
compression. The same template and content draw the same bytes with the same GD. Its `version()` — the renderer's
own and GD's — enters the fingerprint, so that a new GD draws new cards.

### What GD draws, and what it does not

The cards are drawn by GD alone, through FreeType, which draws each character as the font gives it, one after the
other, from left to right, with no text shaping:

| Text | Drawn |
|---|---|
| Latin, Greek and Cyrillic letters, accents included, digits, punctuation, `« »` and `…` | As the font draws them |
| Chinese, Japanese and Korean | As the font draws them; the lines break between characters, by the rules of ICU |
| Arabic, Persian, Urdu | Wrong: each letter in its isolated form, not joined, from left to right instead of right to left |
| Hebrew | Wrong: from left to right instead of right to left |
| Devanagari, Bengali, Tamil and the other scripts of India, Thai, Khmer, Myanmar | Wrong: no conjuncts, vowel signs out of place |
| Emoji in colour, flags, sequences joined by U+200D | Wrong: in the outline the font has, if any, one piece of a sequence after the other |
| A character the font lacks | The font's missing-glyph box |

A site whose titles are in a script of the wrong rows draws its cards with `ImagickCardRenderer`, below, or with
another renderer that shapes text, through the `CardRenderer` port; the default image of the page is another way out.
The font of a template must cover every character its titles use, the ellipsis `…` that ends a cut title included.

### Drawing with ImageMagick

`ImagickCardRenderer` draws the same layout through `ext-imagick`, measured by `ImagickTextMeasure`. When ImageMagick
is built with libraqm, the text is shaped and laid out in its direction: the letters of Arabic, Persian and Urdu join
and run from right to left, Hebrew runs from right to left, and the scripts of India place their vowel signs and
conjuncts. `ImagickCardRenderer::shapes()` tells whether the installed ImageMagick does; without libraqm, ImageMagick
draws as GD does, from left to right and unshaped.

```php
use Phil\SocialCards\Card\{CardGenerator, DirectoryCardStore, ImagickCardRenderer};

$cards = new CardGenerator(new ImagickCardRenderer(), new DirectoryCardStore('/var/cards'));
```

A title or subtitle whose base direction is from right to left — its first letter of a strong direction is Hebrew or
Arabic, as `TextDirection::of()` reads it — is aligned on the right margin; any other on the left. The images of a
template are read by the coder of their type alone, PNG, JPEG, GIF or WebP, never as another format. The PNG is
opaque, at the highest compression, without the dates ImageMagick writes, so that the same template and content draw
the same bytes with the same ImageMagick. Its `version()` — the renderer's own, ImageMagick's, and whether libraqm
shapes — enters the fingerprint: switching renderers, or installing libraqm, draws new cards.

The renderer is optional: `ext-imagick` is suggested, not required. Its fonts are the template's; libraqm shapes
only what the font holds, so a card in Arabic needs a font with Arabic glyphs and their joined forms. A character the
font lacks is left out of the drawing by ImageMagick, where GD draws the missing-glyph box, while its width is still
measured: the text keeps a gap where it would be.

## Fingerprints and stores

`CardGenerator::fingerprint()` hashes, with xxHash128, the renderer's version, the template's fingerprint, the title,
the subtitle and the locale: 32 lower-case hexadecimal digits, which a page puts in the address of its card. A change
of any of them gives a new address, so a card can be cached forever. `generate()` returns the kept card, or draws it
and keeps it.

| Store | Keeps |
|---|---|
| `DirectoryCardStore` | `<directory>/<first two digits>/<fingerprint>.png`, written under a temporary name and renamed into place |
| `InMemoryCardStore` | The cards of the process, until `reset()` |

A store takes only a valid fingerprint: nothing a path could climb out of reaches the file system.
