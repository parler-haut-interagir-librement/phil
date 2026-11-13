# Archiving

```php
use Phil\PagedMedia\Pdf\Conformance;

$pdf = new Document(PageSize::letter(), new Metadata(title: 'Receipt R-001', author: 'The shop'), conformance: Conformance::PdfA2u);
```

A document given `Conformance::PdfA2u` is written to PDF/A-2u (ISO 19005-2, level U): it looks the same in every
reader for as long as the file is kept, and its text can be extracted as Unicode. It is the format for documents a
law asks to keep for years, such as receipts and invoices.

## What the document adds

| Addition | Why |
|---|---|
| An XMP metadata stream, uncompressed, with the conformance and the same title, author, subject, keywords, creator, producer and creation date as the information dictionary | PDF/A identifies itself in XMP, and requires both to agree |
| An sRGB output intent, with an ICC profile written from the colorimetry of IEC 61966-2-1 | Colours given in RGB and grey are defined by it |
| A file identifier in the trailer, the hash of the file's objects | PDF/A requires one; a hash keeps the output deterministic |

Embedded TrueType fonts already carry their map to Unicode, which level U requires.

## What the document refuses

A PDF/A document refuses, as soon as it is drawn, what the standard forbids:

| Refused | Why | Instead |
|---|---|---|
| `StandardFont::Helvetica` and `HelveticaBold` | PDF/A embeds every font, and the standard fonts are not embedded | A `TrueTypeFont` |
| A character the TrueType font lacks | PDF/A forbids printing the missing glyph | A font that has the character |
| A CMYK JPEG | The output intent is sRGB | The image in RGB or grey |

Each refusal throws `InvalidDocument`, naming what is wrong.

## Checking a document

The library's tests check the structure. [veraPDF](https://verapdf.org), the reference validator, checks conformance:

```sh
verapdf --flavour 2u --format text receipt.pdf
```
