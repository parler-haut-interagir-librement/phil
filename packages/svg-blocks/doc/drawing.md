# Drawing blocks

`BlockMarkup` draws the blocks of a document and keeps each drawing with its block:

```json
{"id": "f1", "type": "math", "data": {"latex": "x^2", "rendered": {"markup": "<math …>…</math>", "key": "v1.…"}}}
```

```php
new BlockMarkup(Signer $signer, array $renderers, array $deferred = ['mermaid'])->render(Document $document, bool $withDeferred = false): Rendered
```

Each type of `$renderers` is drawn by its `MarkupRenderer`; blocks of other types are left alone, and blocks nested in
columns, toggles or containers are drawn like top-level ones. For each block of a drawn type:

- a drawing whose key matches the block's current source is kept as it is: saving an unchanged document again draws
  nothing and returns the same `Document`;
- any other drawing — sent by a client, or made for an older source — is dropped, and the block is drawn anew;
- a block of a `$deferred` type is not drawn unless `$withDeferred` is true: its identifier is listed in `pending`;
- a block whose source cannot be drawn keeps no drawing, and its identifier and the reason are listed in `failures`.
  Readers then see the block's own rendering — its code, its formula as text.

`Rendered` holds the `document`, whether it `changed`, the `pending` blocks and the `failures`.

## Sources

| Type | Drawn from | By |
|---|---|---|
| `math` | `latex` | `MathRenderer`, `phil/latex-mathml`, as block MathML |
| `mermaid` | `code` | `MermaidRenderer`, the Mermaid service, sanitized |
| `chart` | the whole configuration but `rendered` | `ChartRenderer`, `phil/svg-chart` |
| `qrCode` | `data` | `QrCodeRenderer`, `phil/qr-code` |

A caption is no part of the source: `phil/ujo` writes it beside the drawing. A drawing is at most
`StoredMarkup::MAX_MARKUP` characters (1 MiB); a larger one is a failure.

## Previews

`preview(Block $block)` draws one block and returns its markup without keeping it, for an author who writes; it throws
`RenderingFailed` for a type that is not drawn or a source that cannot be. `draws($type)` says whether a type is drawn.

## Cost

Drawing happens at save, once per change. Reading checks one HMAC per drawing: three drawings of a page cost about a
tenth of a millisecond, measured by `tests/Benchmark/StoredMarkupBench.php`. `BlockMarkup` keeps no state between
calls and is safe in a worker that serves many requests.
