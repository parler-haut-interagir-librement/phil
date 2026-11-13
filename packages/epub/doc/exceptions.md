# Exceptions

Every exception implements `Phil\Epub\Exception\EpubException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidBook` | `InvalidArgumentException` | An empty title or creator name, a language that is not a language tag, a publication date in another form, an identifier that is empty or is not the UUID, ISBN-13 or DOI it claims to be, a MARC role that is not three lowercase letters, a chapter file name not ending with `.xhtml`, a path outside the package or with other characters, a path used twice or reserved, a media type outside the EPUB core media types, an image or font whose bytes are not recognised, a cover that is not an image or has no text alternative, a table of contents entry without label, contradictory accessibility declarations, a book without chapter, a table of contents depth outside 1 to 6 |
| `WriteFailed` | `RuntimeException` | The archive cannot be created or written, or no temporary file can be made |

Each message names what is wrong and what was given, for example `The path "a.css" is used twice.` or
`"english" is not a language tag such as "en" or "fr-CA".`

HTML that cannot go into a book is not an exception: what EPUB refuses is left out, as [Content documents](content.md)
describes.
