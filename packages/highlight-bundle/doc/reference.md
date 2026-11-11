# Reference

| Class | Role |
|---|---|
| `Phil\HighlightBundle\PhilHighlightBundle` | The bundle: the `phil_highlight` configuration, its services, the stylesheet mapped for AssetMapper, the autoconfiguration of languages and the compiler pass |
| `Phil\HighlightBundle\Infrastructure\DependencyInjection\HighlightServices` | Registers the services; `isName()`, the check of a name; the service identifiers, the tag and the asset namespace as constants |
| `Phil\HighlightBundle\Infrastructure\DependencyInjection\HighlighterPass` | Adds the tagged languages and the aliases to the highlighter; decorates `phil/ujo-bundle`'s `HtmlRenderer`, or removes the code highlighter |
| `Phil\HighlightBundle\Infrastructure\Language\AliasedLanguage` | A `Language` of `phil/highlight` under the configured aliases |
| `Phil\HighlightBundle\Infrastructure\Twig\HighlightExtension` | The `highlight` filter |

| Service | Class |
|---|---|
| `Phil\Highlight\Highlighter` | `Highlighter` |
| `phil_highlight.code_highlighter` | `Closure` |
| `phil_highlight.ujo_renderer` | `Phil\Ujo\Html\HtmlRenderer` |
| `Phil\HighlightBundle\Infrastructure\Twig\HighlightExtension` | `HighlightExtension` |

| Constant | Value |
|---|---|
| `HighlightServices::LANGUAGE_TAG` | `phil_highlight.language` |
| `HighlightServices::ALIASES` | `phil_highlight.aliases`, the parameter of the configured aliases |
| `HighlightServices::ASSET_NAMESPACE` | `phil-highlight` |

| File | Role |
|---|---|
| `assets/styles/highlight.css` | The theme: see [Theme](theme.md) |
