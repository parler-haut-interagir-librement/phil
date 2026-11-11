# Extension points

The library is plain PHP and declares no service. `Markup` is two ports — a compiler and a purifier —, and each
format is read by a reader of its own.

## `MarkupCompiler`

`Phil\Markup\MarkupCompiler` — a source in a format to HTML.

| Method | Contract |
|---|---|
| `compile(string $source, MarkupFormat\|string $format): string` | HTML **not safe to show**: `Markup` purifies it after |

### `LevelAwareCompiler`

`Phil\Markup\LevelAwareCompiler` — a compiler told the level the HTML will be purified at, so that raw HTML is kept
at `all` and escaped below.

| Method | Contract |
|---|---|
| `compileFor(string $source, MarkupFormat\|string $format, Level $level): string` | As `compile()`, for that level |

`Adapter\DefaultCompiler` compiles every format through UJO; it takes the `HtmlRenderer` of `phil/ujo`, so the
application's block renderers render its own blocks. A compiler of the application's own decorates it — to add
smart punctuation, say — and keeps it level aware.

## `HtmlPurifier`

`Phil\Markup\HtmlPurifier` — what remains of HTML at a level.

| Method | Contract |
|---|---|
| `purify(string $html, Level $level): string` | HTML safe to show at that level, whatever it was given |

`Adapter\SanitizerPurifier` holds one `symfony/html-sanitizer` per level: `defaultPolicies($mediaHosts)` builds the
shipped ones, with the hosts images may come from; `new SanitizerPurifier($sanitizers)` takes the application's own.

## Readers

`Phil\Markup\Reader\MarkupReader` — a format read into a UJO document.

| Method | Contract |
|---|---|
| `read(string $source): Reading` | The `Document` and the metadata the source declares; `InvalidSource` only for a format with rules a source can break, as UJO JSON — a reader of a written format never throws, and reads what it can in linear time |

`Reading` also lists what the reader did not keep, `$losses`, each a `Loss` with its element, path and `LossReason`.

## `ExtractedElementStore`

`Phil\Markup\Contract\ExtractedElementStore` — where the HTML reader stores the elements it extracts as files: an inline SVG
drawing, purified by `phil/svg-sanitizer` before it is given.

| Method | Contract |
|---|---|
| `store(string $content, string $mediaType, string $name): ?string` | The address of the stored file — `http`, `https` or relative —, `null` when it is refused; a drawing whose address is refused is left out, the loss recorded |

## Other points

| Point | How |
|---|---|
| The services a frame may be | `EmbedServices` of `phil/ujo`, given to `HtmlReader` |
| Where wiki links lead | `Reader\Wiki\WikiLinks`, given to the wiki readers: the address of a page and of a medium the text names |
| Raw HTML in Markdown | The `$rawHtml` flag of `MarkdownReader` |
| A dialect of Markdown | A `MarkdownDialect` given to `MarkdownReader`: see below |
| Another format | A `ReaderProvider` given to `DefaultCompiler` under the format's name: see below |

The rules of the wiki syntaxes are internal.

## Formats of add-ons

`Phil\Markup\Reader\Contract\ReaderProvider` — the reader of a format the library does not ship.

| Method | Contract |
|---|---|
| `reader(Level $level, Normalizer $normalizer): MarkupReader` | A reader for a source read at that level, raw HTML kept at `all` only, its document normalized by `$normalizer` |

`new DefaultCompiler($renderer, ['name' => $provider])` takes the providers by name. A name is 1 to 16 lower-case
letters, digits and hyphens, starts with a letter, and is not a case of `MarkupFormat`; any other throws
`UnknownFormat`. The compiler then compiles, and `reader()` reads, that format as a built-in one, by its name:
`compile($source, 'name')`; `formats()` lists it after the built-in ones. A `MarkedUpText` stores the name, which fits
its 16 characters.

```php
final readonly class ShoutReaders implements ReaderProvider
{
    public function reader(Level $level, Normalizer $normalizer): MarkupReader
    {
        return new MarkdownReader(Level::All === $level, $normalizer, new ShoutDialect());
    }
}

$compiler = new DefaultCompiler(null, ['shout' => new ShoutReaders()]);
$compiler->compile('Hello!', 'shout');
```

## Dialects of Markdown

`Phil\Markup\Reader\Markdown\Contract\MarkdownDialect` — what a dialect adds to Markdown, or reads differently.
`Reader\Markdown\BaseDialect` reads each point as plain Markdown does; a dialect extends it and overrides what it
changes.

| Method | Contract |
|---|---|
| `inlineRules(): list<InlineRule>` | Tried after the built-in rules of the same characters, in this order |
| `blockRules(): list<BlockRule>` | Tried on a line after the built-in rules, in this order; a line one starts ends the paragraph above |
| `disables(): list<MarkdownSyntax>` | The built-in syntax turned off: `ColonBlocks`, the `:::` blocks |
| `frontMatter(list<string> $lines): ?array` | The metadata before the text and the lines after it; `null` for the YAML front matter |
| `define(string $label, string $value, Context $context): bool` | `true` when the dialect reads a `[label]: value` line itself, which then makes no link reference |
| `definition(string $line): ?string` | The text of a definition a line starts under a term, without its mark; `: ` is tried first |
| `paragraph(string $text): array{string, array}` | The text of a paragraph without its mark, and the tunes the mark gives it |

`Reader\Markdown\Rule\InlineRule` — one kind of inline token.

| Method | Contract |
|---|---|
| `triggers(): string` | The characters a token starts with |
| `lex(Cursor $cursor, InlineParser $parser): Delimiter\|string\|null` | The HTML of the token, escaped by the rule — `InlineParser::escape()`, `$parser->parse()` for nested Markdown —, or a `Delimiter` to pair into emphasis, consumed; `null`, and the cursor where it was, when none starts there |

`new Rule\DelimiterRule('^')` reads `^sup^` and `^^sub^^`, the exponents the built-in rule leaves out.

`Reader\Markdown\Contract\BlockRule` — one kind of block.

| Method | Contract |
|---|---|
| `read(list<string> $lines, int $at, Context $context): ?array` | The blocks the line at `$at` starts, made by `Context::block()`, their inline content as `Inline`, and the index of the line after them; `null` when the line starts none |

A dialect is shared by every reading and keeps nothing: what it learns of one source — variables, say — goes into
`Context::$extra`, which lives as long as the reading. Its rules read with what the reader reads with: `Lexer\Cursor`,
`Attributes` for braces, `Curly` for the `{{ }}` blocks and spans and `Curly::scan()` for a span on its line,
`FrontMatter::yaml()` for YAML. A rule reads in time linear in what it consumes, and nests to a bound of its own.
