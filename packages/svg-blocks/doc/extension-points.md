# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Draw another block type, or draw one differently | Implement `Contract\MarkupRenderer` and give it to `BlockMarkup` under the type |
| Call another diagram service | Implement `Contract\MermaidService` |
| Draw a type only in the background | Name it in `BlockMarkup`'s `$deferred` |
| Size charts, QR codes or formulas | Give the renderers a configured `SvgChartWriter`, `SvgWriter` and level, or `LatexToMathMl` |

A renderer must return markup safe to write in a page: the drawing is signed, then written without being sanitized
again.
