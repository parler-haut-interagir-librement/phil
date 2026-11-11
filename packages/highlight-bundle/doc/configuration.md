# Configuration

```yaml
# config/packages/phil_highlight.yaml
phil_highlight:
    aliases:              # more names for a language, each to the name it stands for
        vue: html
        jsonc: json
    ujo:
        enabled: true     # colour the code blocks of UJO documents, when phil/ujo-bundle is installed
```

| Option | Default | Effect |
|---|---|---|
| `aliases` | `{}` | More names a language is asked for by, each to the name or alias of a language the highlighter reads: a shipped language or one of the application's. See [Languages](languages.md) |
| `ujo.enabled` | `true` | `false`, or `ujo: false`, leaves the code blocks of UJO documents escaped, for the browser to colour |

A name is written as the highlighter accepts it: in lower case, with letters, digits and `_+#.-`, up to 32
characters. Any other alias or name is refused when the container is built. A name that no language has is found
when the highlighter is first built, by an [`UnknownLanguage`](exceptions.md).

## Services

| Service | Built as |
|---|---|
| `Phil\Highlight\Highlighter` | `Highlighter::default()`, then `with()` each tagged language and each group of aliases; public |
| `phil_highlight.code_highlighter` | `Closure::fromCallable([Highlighter, 'highlight'])`, when `ujo.enabled` and `phil/ujo-bundle`'s renderer is a service |
| `phil_highlight.ujo_renderer` | Decorates `Phil\Ujo\Html\HtmlRenderer` with `withCodeHighlighter()` and that closure |
| `Phil\HighlightBundle\Infrastructure\Twig\HighlightExtension` | The filter, when Twig is installed |

Inject the highlighter where code is coloured outside templates:

```php
public function __construct(private Highlighter $highlighter)
{
}
```

Every service implementing `Phil\Highlight\Language` is tagged `phil_highlight.language` when autoconfigured.
