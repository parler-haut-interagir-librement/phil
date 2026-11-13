# Exceptions

The bundle's exceptions implement `Phil\Fonts\Exception\FontsException`, like the library's.

| Exception | Thrown when |
|---|---|
| `FontUnreadable` (`RuntimeException`) | A font file the library cannot read — a WOFF2 file without `ext-brotli`, a damaged file — or a licence register that is not valid JSON; the message names the file, the previous exception is the library's |
| `FontNotFound` (`InvalidArgumentException`) | A path the catalogue has no file at; a family, weight and style no file holds in the formats asked |

The catalogue never throws for a file it cannot read: it keeps the reason in the entry. The native writer's own
`Phil\PagedMedia\Exception\InvalidFont` passes through `PdfFonts` for a font whose licence forbids embedding.

A configuration Symfony refuses — an empty directory, a `font_display` CSS does not know — throws
`InvalidConfigurationException` when the container is built.
