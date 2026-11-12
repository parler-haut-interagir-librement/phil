# Extension points

The library declares no service. What an application may implement or replace is the drawing and the keeping of the
cards.

## `CardRenderer`

`Phil\SocialCards\Contract\CardRenderer` — draws a card. `GdCardRenderer` is the shipped one; an Imagick renderer, or
one that shapes complex scripts, implements it.

| Method | Contract |
|---|---|
| `version(): string` | What the image depends on besides the template and the content; it enters every fingerprint |
| `render(CardTemplate $template, CardContent $content): string` | A PNG of 1200×630, the same bytes for the same arguments and version |

## `TextMeasure`

`Phil\SocialCards\Contract\TextMeasure` — measures a line as the renderer draws it, for `TextFitter`.

| Method | Contract |
|---|---|
| `width(string $text, CardFont $font, int $size): int` | The width in pixels at a size in pixels; 0 for the empty text |

## `CardStore`

`Phil\SocialCards\Contract\CardStore` — keeps the drawn cards by fingerprint. `DirectoryCardStore` and
`InMemoryCardStore` are shipped; a store on object storage implements it.

| Method | Contract |
|---|---|
| `get(string $fingerprint): ?string` | The PNG, or null |
| `put(string $fingerprint, string $png): void` | Keeps the PNG; a reader never sees half a card |

A store refuses what is not a fingerprint, with `Fingerprint::check()`.
