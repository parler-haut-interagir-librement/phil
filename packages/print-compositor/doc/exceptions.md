# Exceptions

Every exception implements `Phil\PrintCompositor\Exception\PrintCompositorException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidLayout` | `InvalidArgumentException` | A size or a resolution out of range, a box outside its zone or not inside a zone, a pixel outside its image, a colour channel or a WebP quality out of range, a blank name, a zone a photo does not place, an ink a job has no layer in, an item that is neither an image nor a text |
| `InvalidImage` | `InvalidArgumentException` | Bytes GD cannot decode, an image file that cannot be read, an image of more pixels than the limit |
| `UnsupportedFont` | `InvalidArgumentException` | A font file that cannot be read or is not a font, a WOFF, a WOFF2 or a collection |
| `MissingGlyphs` | `DomainException` | A text with characters its font does not cover |
| `TextDoesNotFit` | `DomainException` | A text that does not fit its box even at its minimum size |
| `MissingCapability` | `RuntimeException` | A GD build without FreeType, PNG or WebP support, when a call needs it |

Each message names what is wrong and what was given, for example
`The text "Rest in pieces" does not fit its 20 × 6 mm box, even at 14 pt.` or
`The font "Example Serif" has no glyph for "€" (U+20AC) in the text "5 €".`
`MissingGlyphs` and `TextDoesNotFit` are the two a customer's text can cause: an application shows them, in its own
words, next to the field the text was entered in.
