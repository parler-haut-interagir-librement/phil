# Reference

## Types

| Type | Kind | Role |
|---|---|---|
| `MarkupFormat` | enum | `Markdown`, `Html`, `BBCode`, `MediaWiki`, `DokuWiki`, `Creole`, `Text`, `Ujo`; `values()`; `nameOf()`, the name of a case or of a format an add-on brings |
| `Level` | enum | `Basic`, `Forum`, `All` |
| `MarkupCompiler` | interface | `compile(string $source, MarkupFormat\|string $format): string`, HTML **not safe to show**; a string names a format of an add-on |
| `LevelAwareCompiler` | interface | A `MarkupCompiler` with `compileFor($source, $format, $level)`: told the level the HTML will be purified at |
| `HtmlPurifier` | interface | `purify(string $html, Level $level): string` |
| `Exception\MarkupException`, `Exception\InvalidSource`, `Exception\UnknownFormat` | exceptions | See [Exceptions](exceptions.md) |
| `Markup` | `final readonly class` | `toHtml()` and `text()`: compile — at the level, for a `LevelAwareCompiler` —, then purify; `Level::All` unless told |
| `MarkedUpText` | embeddable | `source` (text), `format` (the name of the format, 16 characters), `html` (text) |
| `Lexer\Cursor` | `final class` | The cursor of the analyser: see [The analyser](analyser.md) |
| `Lexer\Slugger` | `final class` | The anchors of one document, unique |
| `Reader\MarkupReader` | interface | `read(string $source): Reading` |
| `Reader\Reading` | `final readonly class` | `document`, a UJO `Document`, `metadata` and `losses` |
| `Reader\Loss`, `Reader\LossReason` | `final readonly class`, enum | What a reader did not keep: element, path, reason |
| `Contract\ExtractedElementStore` | interface | `store(string $content, string $mediaType, string $name): ?string`: where extracted elements are stored as files |
| `Reader\Markdown\MarkdownReader` | `MarkupReader` | Markdown, and the syntax of a dialect: see [Markdown](markdown.md) |
| `Reader\Markdown\Contract\MarkdownDialect`, `Reader\Markdown\BaseDialect` | interface, `abstract class` | A dialect of Markdown, and one that reads as plain Markdown, to extend: see [Extension points](extension-points.md#dialects-of-markdown) |
| `Reader\Markdown\Contract\BlockRule`, `Reader\Markdown\Rule\InlineRule` | interfaces | A block and an inline rule of a dialect |
| `Reader\Markdown\MarkdownSyntax` | enum | The built-in syntax a dialect may turn off: `ColonBlocks` |
| `Reader\Markdown\Context`, `InlineParser`, `Inline`, `Delimiter`, `Attributes`, `Curly`, `FrontMatter`, `Rule\DelimiterRule` | classes | What the rules of a dialect read with and make: see [Extension points](extension-points.md#dialects-of-markdown) |
| `Reader\Contract\ReaderProvider` | interface | `reader(Level $level, Normalizer $normalizer): MarkupReader`, the reader of a format an add-on brings |
| `Reader\BBCode\BBCodeReader` | `MarkupReader` | BBCode and its extensions: see [BBCode](bbcode.md) |
| `Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader`, `Reader\Creole\CreoleReader` | `MarkupReader` | The wikis and their extensions: see [The wikis](wikis.md) |
| `Reader\Wiki\WikiLinks` | `final readonly class` | Where the pages and the media a wiki text names are found: `pages`, `media` |
| `Reader\Html\HtmlReader` | `MarkupReader` | HTML into UJO: see [HTML](html.md) |
| `Reader\UjoReader` | `MarkupReader` | UJO JSON, through `phil/ujo` |
| `Reader\Text\TextReader` | `MarkupReader` | Plain text: a `paragraph` for each run of lines, line breaks kept, addresses made links |
| `Adapter\DefaultCompiler` | `LevelAwareCompiler` | Every format, the add-ons' given by name; `reader($format, $level)` gives the reader of a format into UJO — HTML's and plain text's too, though both are compiled without it —; `formats()` the name of each |
| `Adapter\SanitizerPurifier` | `HtmlPurifier` | `defaultPolicies()`, one sanitizer per level |
| `Adapter\CodeClasses` | `final readonly class` | The classes kept on `code` (`language-…`) and `span` (`hl-…`), an attribute sanitizer of `symfony/html-sanitizer` |
| `Adapter\PlainText`, `Adapter\Autolink` | compilers | Plain text, links in text |

The root namespace — formats, levels, ports and exceptions — is what a domain model may name; the analyser, the
readers, the adapters, `Markup` and `MarkedUpText` belong with the infrastructure. The classes of
`Reader\Markdown` and the rules under `Reader\Markdown\Rule` that the table does not name, the classes of
`Reader\BBCode` other than `BBCodeReader`, those of `Reader\Wiki` other than `WikiLinks`, the syntaxes of the wikis and
the walker of `Reader\Html`, are internal.

## Formats

| Format | Compiled |
|---|---|
| `markdown` | read into UJO by `MarkdownReader`, then rendered by `phil/ujo`; raw HTML kept at `all`, escaped below |
| `ujo` | read and checked by `phil/ujo`, then rendered; a source that is not valid throws `InvalidSource` |
| `html` | as written; `HtmlReader` converts it into UJO on demand: see [HTML](html.md) |
| `bbcode` | read into UJO by `BBCodeReader`, then rendered; raw HTML as for Markdown: see [BBCode](bbcode.md) |
| `mediawiki`, `dokuwiki`, `creole` | read into UJO by `MediaWikiReader`, `DokuWikiReader`, `CreoleReader`, then rendered; raw HTML as for Markdown: see [The wikis](wikis.md) |
| `text` | escaped; a paragraph per block separated by a blank line, a line break per other new line, addresses made links; `TextReader` converts it into UJO paragraphs on demand |
| a format of an add-on | read into UJO by the reader its `ReaderProvider` gives, then rendered |

The HTML `phil/ujo` renders carries the classes of its blocks — `ujo-paragraph`, `ujo-header` — and the identifiers of
headings. The purifier of every level removes the classes but those of code (`Adapter\CodeClasses`): `language-…` on
`code`, which names the language of a block for a highlighter in the browser, and `hl-…` on `span`, which a
highlighter on the server writes, where spans are kept; `all` keeps the identifiers, which a table of contents links
to. [Formats](formats.md) lists, feature by feature, how each format writes it.

## Levels

| Level | Keeps | For |
|---|---|---|
| `basic` | `strong`, `b`, `em`, `i`, `u`, `s`, `del`, `sub`, `sup`, `code`, `br` | a name, a caption, a short comment |
| `forum` | basic, with `p`, `a`, `img`, `ul`, `ol`, `li`, `blockquote`, `cite`, `pre`, `hr` | a message of a forum |
| `all` | every element `symfony/html-sanitizer` counts as safe, headings and tables included | an article an editor writes |

A container a level does not allow — a paragraph, a list, a heading, a table — is removed and its text kept; any
other element — a script, a frame, a form, an object — is removed with its content. Every level removes attributes
it does not know, styles and event handlers among them. Links may use `http`, `https` and `mailto`, or be relative;
images `http` and `https`, or be relative. **The input length is not capped.**

**An image is a request to its host.** At `forum` and `all`, an image of another site — `[img]https://…[/img]`,
`![](https://…)`, `<img src="https://…">` — makes the browser of every reader call that site, which learns their
address and when they read: tracking without their consent when a member wrote the image, which Law 25 and the GDPR
forbid without it. `SanitizerPurifier::defaultPolicies()` takes the hosts images may come from:

```php
SanitizerPurifier::defaultPolicies(['cdn.example.com']);   // that host and its subdomains, and the site's own images
SanitizerPurifier::defaultPolicies([]);                    // the site's own images only
SanitizerPurifier::defaultPolicies();                      // any host
```

An image from another host loses its `src`; a relative image, served by the site itself, is always kept. A site whose
members write texts at `forum` gives the hosts it serves images from, or `[]`.

Purify again what was stored when it is shown: a policy that tightens then applies to texts written before.
