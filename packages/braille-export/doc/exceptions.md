# Exceptions

Every exception of the package implements `Exception\BrailleExportException`; those of a translation that could not be
done implement `Exception\TranslationFailed`, which the translators of other packages implement too.

| Exception | When |
|---|---|
| `UnsupportedTranslation` | No translator supports the language with the contraction asked. |
| `UntranslatableCharacter` | The strict `UncontractedTranslator` met a character it has no braille for; `$character` and `$language` name it. A `TranslationFailed`. |
| `InvalidBraille` | A translator broke the port: not one text per text sent, or characters that are not braille cells. |
| `DocumentTooLong` | The document fills more pages than `PageFormat::$maxPages`. |
| `UnrepresentableCell` | `BrfWriter` met a cell with dot 7 or 8. |

`InvalidArgumentException` stops a value built out of its bounds: a `PageFormat`, a heading level outside 1 to 6, an
ordered list starting below 0, dots that are not 1 to 8.

The messages name the failure, never the text of the document.
