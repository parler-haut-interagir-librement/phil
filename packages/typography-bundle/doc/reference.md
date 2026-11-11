# Reference

| Class | Role |
|---|---|
| `Phil\TypographyBundle\PhilTypographyBundle` | The bundle: the `phil_typography` configuration, its services and the compiler pass |
| `Phil\TypographyBundle\Infrastructure\DependencyInjection\TypographyServices` | Registers the services; the service identifiers as constants |
| `Phil\TypographyBundle\Infrastructure\DependencyInjection\UjoFilterPass` | Decorates `phil/ujo-bundle`'s `HtmlRenderer` with the typographic filter, or removes the filter |
| `Phil\TypographyBundle\Infrastructure\Twig\TypographyExtension` | The `typography` and `typography_text` filters |
| `Phil\TypographyBundle\Infrastructure\Ujo\TypographyHtmlFilter` | The `HtmlFilter` of `phil/ujo` that runs a typographer |

| Service identifier | Class |
|---|---|
| `Phil\Typography\Hyphenation\Dictionary` | `Dictionary` |
| `Phil\Typography\Typographers` | `Typographers` |
| `Phil\Typography\Typographer` | `Typographer` |
| `phil_typography.typographer.<locale>` | `Typographer` |
| `phil_typography.ujo_filter` | `TypographyHtmlFilter` |
| `phil_typography.ujo_renderer` | `Phil\Ujo\Html\HtmlRenderer`, decorating the renderer of `phil/ujo-bundle` |
