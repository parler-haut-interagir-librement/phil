# Configuration

```yaml
# config/packages/phil_ujo.yaml
phil_ujo:
    allow_raw: false        # whether documents may hold raw HTML blocks
    max_bytes: 4194304      # the largest UJO JSON read, in bytes
    max_depth: 8            # the deepest nesting of blocks inside blocks
    max_blocks: 10000       # the most blocks a document may hold, nested ones included
    max_html_length: 100000 # the longest HTML field, in characters
    max_raw_length: 50000   # the longest HTML of a raw block, in characters
```

| Option | Default | Effect |
|---|---|---|
| `allow_raw` | `false` | `true` adds the `raw` type to the registry: its HTML is sanitized with the `raw` profile. Accept it only from writers you trust with page structure |
| `max_bytes` | `4194304` | A larger JSON is refused before parsing. At least 1 |
| `max_depth` | `8` | Blocks nested deeper are refused. At least 1 |
| `max_blocks` | `10000` | A document of more blocks, nested ones included, is refused. At least 1 |
| `max_html_length` | `100000` | An HTML field longer, in characters, is refused before it is sanitized. At least 1 |
| `max_raw_length` | `50000` | The same for the HTML of a `raw` block. At least 1 |

The block and length limits bound what showing a document costs: its HTML is sanitized again at each render, through
`ujo_html` as through `Ujo::toHtml()`.

## Services

| Service | Built as |
|---|---|
| `Phil\Ujo\Type\BlockTypeRegistry` | `BlockTypeRegistry::default()`, with `StandardTypes::raw()` when `allow_raw` |
| `Phil\Ujo\Normalizer` | on that registry, the default tunes and sanitizer, `max_depth`, `max_blocks`, `max_html_length` and `max_raw_length` |
| `Phil\Ujo\Html\HtmlRenderer` | `HtmlRenderer::default()` |
| `Phil\Ujo\Text\PlainText` | with the default conversions |
| `Phil\Ujo\Json\JsonDecoder` | with `max_bytes` |
| `Phil\Ujo\Ujo` | on the four above; public |

Registries and renderers are immutable values. To add a type, a tune, a renderer or a conversion, build the value
the application needs in a factory of its own and declare that factory for the service:

```php
// src/Ujo/UjoFactory.php
final class UjoFactory
{
    public static function types(): BlockTypeRegistry
    {
        return BlockTypeRegistry::default()->with(CalloutType::create());
    }

    public static function renderer(): HtmlRenderer
    {
        return HtmlRenderer::default()->with('callout', new CalloutRenderer());
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\Ujo\Type\BlockTypeRegistry:
        factory: ['App\Ujo\UjoFactory', 'types']
    Phil\Ujo\Html\HtmlRenderer:
        factory: ['App\Ujo\UjoFactory', 'renderer']
```

The other services, `Ujo` first, take the redefined ones by reference.
