# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\Highlight\Highlighter` | `final readonly class` | The languages by name and alias; tokens, HTML, and `highlight()` for code renderers |
| `Phil\Highlight\Language` | interface | A tokenizer: name, aliases, tokens |
| `Phil\Highlight\Token` | `final readonly class` | A piece of the source and its type |
| `Phil\Highlight\TokenType` | enum | What a token is; its HTML class |
| `Phil\Highlight\HtmlFormatter` | `final class` | Tokens as escaped HTML spans, at once or line by line |
| `Phil\Highlight\LineOptions` | `final readonly class` | How a block is written line by line: numbers, highlighted lines, annotations |
| `Phil\Highlight\LineRanges` | `final readonly class` | Lines of a block, single and in ranges, read from `1,3-5` |
| `Phil\Highlight\LineMark` | enum | What a line is marked as; its HTML class |
| `Phil\Highlight\Language\Code` | `final readonly class` | The tokenizer of a programming language described by a `Syntax` |
| `Phil\Highlight\Language\Syntax` | `final readonly class` | What a programming language looks like to `Code` |
| `Phil\Highlight\Language\Quote` | `final readonly class` | A kind of quoted text |
| `Phil\Highlight\Language\Definitions` | `final class` | The syntaxes shipped |
| `Phil\Highlight\Language\Markup` | `final readonly class` | HTML and XML, with embedded languages |
| `Phil\Highlight\Language\StyleSheet` | `final readonly class` | CSS and SCSS |
| `Phil\Highlight\Language\Yaml` | `final readonly class` | YAML |
| `Phil\Highlight\Language\Ini` | `final readonly class` | INI and its kin |
| `Phil\Highlight\Language\Dockerfile` | `final readonly class` | Dockerfiles, arguments read by a shell language |
| `Phil\Highlight\Language\Diff` | `final readonly class` | Diffs and patches |
| `Phil\Highlight\Language\Markdown` | `final readonly class` | Markdown |
| `Phil\Highlight\Language\Twig` | `final readonly class` | Twig, with markup and expression languages |
| `Phil\Highlight\Language\Php` | `final readonly class` | PHP, code and markup mixed |
| `Phil\Highlight\Scanner\Cursor` | `final class`, internal | A position in a source, read byte by byte |
| `Phil\Highlight\Scanner\Output` | `final class`, internal | Tokens joined by type |
| `Phil\Highlight\Exception\HighlightException` | interface | Every exception of the library |
| `Phil\Highlight\Exception\UnknownLanguage` | `final class` | A language asked for by a name not known |
| `Phil\Highlight\Exception\InvalidLanguage` | `final class` | A language added under a name that cannot be asked for |
| `Phil\Highlight\Exception\InvalidLineRanges` | `final class` | Line ranges or a first line number that cannot be read |

## Files

| Path | Role |
|---|---|
| `themes/light.css`, `themes/dark.css`, `themes/auto.css` | The themes of the `hl-` classes |
