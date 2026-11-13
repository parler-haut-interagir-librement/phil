# Exceptions

The bundle's exceptions implement `Phil\PrintPreflight\Exception\PrintPreflightException`, like the library's.

| Exception | Thrown when |
|---|---|
| `InvalidMeasurement` (`InvalidArgumentException`) | A measured document that is not JSON, a field missing or of the wrong type, an unknown unit, colour space or style, a font named by family without a resolver, a refusal of the library's model; a length or size the configuration writes wrong; a medium that is not an image of known size |
| `UnknownProfile` (`InvalidArgumentException`) | A profile name the configuration does not declare, the names it does in the message |

The library's `InvalidProfile` comes out of the configuration as `InvalidConfigurationException` when the container
is built. The writer's `Phil\PagedMedia\Exception\InvalidDocument` passes through `PdfLayout` as it is — a drawing
before the first page, a font size of 0 —, and `FontNotFound` of `phil/fonts-bundle` through `CatalogueFontResolver`.
