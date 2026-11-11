# Extension points

What an application configures, redefines or decorates. The types, renderers and filters are `phil/ujo`'s.

## Configuration

| Option | What it extends |
|---|---|
| `allow_raw` | Whether the `raw` type is in the registry, its HTML sanitized |
| `max_bytes`, `max_depth`, `max_blocks`, `max_html_length`, `max_raw_length` | The bounds of what is read |

See [Configuration](configuration.md).

## Services

Registries and renderers are immutable values: an application redefines the service with a factory of its own that
builds on the default, and every other service — `Ujo` first — takes the redefined one by reference. See
[Configuration](configuration.md#services).

| Service | How an application extends it |
|---|---|
| `Phil\Ujo\Type\BlockTypeRegistry` | Its own block types: `BlockTypeRegistry::default()->with(...)` |
| `Phil\Ujo\Html\HtmlRenderer` | Its own block renderers with `with()`, a code highlighter with `withCodeHighlighter()`, passes over the HTML with `withFilter()`; other bundles decorate it to add theirs, and decorations compose |
| `Phil\Ujo\Normalizer` | The tunes and the inline sanitizer of its documents |
| `Phil\Ujo\Text\PlainText` | Its own conversions to text |
| `Phil\Ujo\Ujo` | Injected wherever documents are read or rendered; public |

## Forms and validation

| Point | How |
|---|---|
| The model of a field | The `document` option of `UjoType`: a `Document` or its canonical JSON |
| The block types accepted | The `types` of `ValidUjo`; a block of another type, nested ones included, is a violation |
| Messages | The field's `invalid_message`, the constraint's `message` |

See [Forms and validation](forms-and-validation.md).
