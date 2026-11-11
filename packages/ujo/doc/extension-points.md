# Extension points

The library is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Accept a type of your own | Add a `BlockType` to a `BlockTypeRegistry` and give it to `Normalizer`; see [Normalization](normalization.md) |
| Ask more of a type before publication | `Property::filled()`, a `Bounded` field, or the `publication` hook of `BlockType`; see [Normalization](normalization.md#validation-levels) |
| Bound a field of your own | Implement `Contract\Sized` and wrap the field in `Bounded` |
| Accept a tune of your own | Add a `TuneType` to a `TuneRegistry` |
| Render a type differently, or render a new one | Implement `Html\BlockRenderer` and pass it to `HtmlRenderer::with()`; see [HTML rendering](html-rendering.md) |
| Colour code blocks on the server | `HtmlRenderer::withCodeHighlighter()` |
| Draw blocks from the markup they keep | `HtmlRenderer::withStoredMarkup()`, with a closure that checks the key |
| Pass over the rendered HTML | Implement `Html\HtmlFilter` and pass it to `HtmlRenderer::withFilter()` |
| Convert a type to text differently | Replace its conversion in `PlainText`; see [Plain text](plain-text.md) |
| Sanitize inline HTML with other rules | Build an `InlineSanitizer` with profiles of your own, by name, and give it to `HtmlRenderer::default()` |
