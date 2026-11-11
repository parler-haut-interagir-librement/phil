# Embedding permissions

A font's licence decides whether a document may carry it. The OpenType `OS/2` table states it in its `fsType` field,
which `EmbeddingPermissions` reads.

## Usage

| `EmbeddingUsage` | `fsType` bit | What a document may do |
|---|---|---|
| `Installable` | none of bits 1 to 3 | Embed the font, and let the reader install it for good |
| `Editable` | bit 3, `0x0008` | Embed the font and be edited with it; the font goes away with the document |
| `PreviewAndPrint` | bit 2, `0x0004` | Embed the font to view and print the document, which stays read-only |
| `Restricted` | bit 1, `0x0002` | Not embed the font without its owner's own permission |

`allowsEmbedding()` is false for `Restricted` only; `allowsEditing()` is true for `Installable` and `Editable`.

From `OS/2` version 3 on, a font sets at most one of these bits. An older one may set several, and the OpenType
specification then asks for the least restrictive: `Editable` wins over `PreviewAndPrint`, which wins over
`Restricted`. A face without an `OS/2` table declares nothing, which reads as installable.

## Subsetting and bitmaps

| Method | `fsType` bit | Meaning |
|---|---|---|
| `allowsSubsetting()` | bit 8, `0x0100`, clear | A document may embed only the glyphs it uses; when set, it must embed the whole font |
| `bitmapOnly()` | bit 9, `0x0200`, set | A document may embed only the font's bitmaps, never its outlines |
| `allowsOutlineEmbedding()` | | The usage allows embedding and the font is not bitmap only: what a PDF or EPUB writer needs |

`fsType` itself stays available, for the reserved bits a later revision of OpenType may define.

```php
$permissions = FontFile::fromFile($path)->face()->embedding;

if (!$permissions->allowsOutlineEmbedding()) {
    throw new RuntimeException('This font may not be embedded in a PDF.');
}

$subset = $permissions->allowsSubsetting();
```

These bits are what the font says. The licence the font was obtained under binds, and may grant more or less: a
register of licences (see [Licences](licences.md)) is where an application records what it was told.

## phil/paged-media

`phil/paged-media` reads TrueType fonts to measure and embed them in PDF documents, and reads the same `fsType` bits to
refuse a font whose licence forbids embedding and to embed whole a font that forbids subsetting. The two libraries do
not depend on each other, and neither copies the other:

- paged-media reads the tables an embedder needs — glyph outlines, metrics, the character map to glyphs — and promises
  a PDF writer with no dependency; phil/fonts reads the tables a catalogue needs — every name, the licence, the
  variation axes, the containers WOFF and WOFF2 — and builds nothing.
- What they share is small: the table directory, a few fields of `OS/2`, the PostScript name.

If they come to share the reading of fonts, the dependency goes from paged-media to phil/fonts — the document writer
using the metadata reader, the lower layer — and never the other way: phil/fonts has no reason to know about PDF. Until
then, an application that embeds fonts reads a font's permissions here to show them, and lets paged-media enforce them
when it writes.
