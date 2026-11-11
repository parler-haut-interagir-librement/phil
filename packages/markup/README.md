# phil/markup

**Markdown, BBCode, MediaWiki, DokuWiki, Creole, UJO, HTML or plain text, turned into HTML that is safe to show.**

phil/markup reads a text written in Markdown, in BBCode, in the syntax of MediaWiki,
DokuWiki or Creole, or in UJO (Universal JSON Output), the block document format of `phil/ujo`, into a UJO document,
renders it as HTML with `phil/ujo`, then purifies that HTML at a level. Its Markdown reader is an analyser of its own:
CommonMark, the GitHub extensions, footnotes, attributes, definition lists, heading anchors, a table of contents, front
matter, `:::` and `{{ }}` blocks, pages, columns, boxes and stat blocks, each mapped to the UJO type of the same role.
Its BBCode reader, on the same analyser, reads the tags of forums and extensions in their style — `[footnote]`,
`[columns]`, `[pagebreak]`, `[statblock]`, `[toc]`, `[note]`, attributes — that give BBCode every feature of the
Markdown reader, in time linear in the source. Its readers of MediaWiki, DokuWiki and Creole read the native syntax of
each wiki and the extensions they share, written as each wiki writes its own — tags `<note>`, `<ref>`, templates
`{{TOC}}`, macros `~~PAGEBREAK~~`, placeholders `<<footnote>>` —, every feature of the Markdown reader again. HTML is
compiled as written, and its reader converts it into UJO — the HTML of `phil/ujo` read back into its blocks, a paste and
older texts into theirs —; plain text is compiled straight to HTML, and its reader converts it into UJO paragraphs.
`basic` keeps inline emphasis, for a name or a comment; `forum` adds paragraphs, links, lists, quotes and images, for a
forum message; `all` keeps every element the sanitizer counts as safe, for an article an editor writes. `MarkedUpText`
keeps the source, its format and the purified HTML together as a Doctrine embeddable, so a page is not compiled on every
read. It does not cap the length of the input. An add-on brings a dialect of Markdown — its inline and block rules,
the syntax it reads differently — or a whole format, read by a reader of its own under a name of its own.

It is for any PHP application that shows text its visitors, members or editors write — comments, forum messages,
profiles, articles, game books — and for one that converts what was written in a markup language to the blocks of a
block editor. Text from people becomes HTML only after purification, and stored HTML can be purified again when it is
shown, so a tightened policy reaches older texts too. In a Symfony application, phil/markup-bundle registers the
services and adds the `markup_html` and `markup_purify` Twig filters. phil/content-bundle compiles and purifies its body
of text with it.

It is a plain PHP library with Doctrine attributes on `MarkedUpText`, read only when Doctrine
maps it. Its root namespace holds what a domain model may name: the `MarkupFormat` and `Level` enums, the
`MarkupCompiler`, `LevelAwareCompiler` and `HtmlPurifier` ports and the exceptions. `Lexer` holds the analyser every
reader shares, a cursor that lexes without regular expressions; `Reader` the readers into UJO; `Adapter` the
compilers and the purifier. `DefaultCompiler` reads or compiles every format, and `SanitizerPurifier` applies one
`symfony/html-sanitizer` policy per level, and takes the readers of the add-ons' formats by name. `Markup` chains the
two: source, reader, UJO, renderer, HTML, purifier.

## Status

| Part | State |
|---|---|
| `MarkupFormat`, `Level`, `MarkupCompiler`, `LevelAwareCompiler`, `HtmlPurifier`, `InvalidSource` | Implemented and tested |
| `Markup`, `MarkedUpText` (a Doctrine embeddable) | Implemented and tested |
| `Lexer\Cursor`, `Lexer\Slugger`: the shared analyser | Implemented and tested |
| `Reader\Markdown\MarkdownReader`: CommonMark, GitHub, footnotes, attributes, definition lists, anchors, `[TOC]`, front matter, `:::` blocks, raw HTML per level | Implemented and tested |
| `{{ }}` blocks and the spans that are blocks, stat blocks, pages and columns | Implemented and tested |
| `MarkdownDialect`, `BaseDialect`, `BlockRule`, `InlineRule`, `ReaderProvider`: the dialects and formats of add-ons | Implemented and tested |
| `Reader\BBCode\BBCodeReader`: the tags of forums and the extensions of every Markdown feature, raw HTML per level, linear time | Implemented and tested |
| `Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader`, `Reader\Creole\CreoleReader`: the native syntax of each wiki and the shared extensions of every Markdown feature, `Reader\Wiki\WikiLinks`, raw HTML per level, linear time | Implemented and tested |
| `Reader\Html\HtmlReader`: HTML into UJO, the HTML of `phil/ujo` read back, pastes and older texts | Implemented and tested |
| `Reader\UjoReader`, `Reader\Text\TextReader` | Implemented and tested |
| `Adapter\DefaultCompiler`: Markdown, BBCode, MediaWiki, DokuWiki, Creole, UJO and the add-ons' formats through UJO; HTML and plain text as written, their readers on demand | Implemented and tested |
| `Adapter\SanitizerPurifier`: one policy per level | Implemented and tested |
| Mentions, embeds, external links, smart punctuation, in every format | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Formats](doc/formats.md) | Every format, and which feature each writes and how: the correspondence table. |
| [Markdown](doc/markdown.md) | What the Markdown reader reads, and the UJO block each construct becomes. |
| [The analyser](doc/analyser.md) | The cursor, the rules, the two passes, and the limits that keep reading linear. |
| [BBCode](doc/bbcode.md) | The tags and the extensions, their UJO types, how a source that is not well formed is read, and why reading is linear. |
| [The wikis](doc/wikis.md) | MediaWiki, DokuWiki and Creole: their syntax, the extension tags they share, where pages and files are found, and why reading is linear. |
| [HTML](doc/html.md) | What the HTML reader reads, the classes of `phil/ujo` it reads back, and what it leaves out. |
| [Exceptions](doc/exceptions.md) | `MarkupException`, `InvalidSource` and `UnknownFormat`. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces, the dialects of Markdown and the formats of add-ons. |
| [Testing](doc/testing.md) | How the library is tested. |
| [Reference](doc/reference.md) | The types, the formats, the levels and what each keeps. |

## Quick start

```php
$markup = new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies());

$markup->toHtml('[b]Bonjour[/b] https://example.com', MarkupFormat::BBCode, Level::Forum);
// '<p><b>Bonjour</b> <a href="https://example.com">https://example.com</a></p>'

$post->body = $markup->text($source, MarkupFormat::Markdown);   // source, format and purified HTML
```

```php
$reading = new MarkdownReader()->read("---\ntitle: Notes\n---\n# Hello *world*");

$reading->metadata;                  // ['title' => 'Notes']
$reading->document->blocks[0];       // a `header` block, level 1, its anchor `hello-world`
```

```php
#[ORM\Embedded(class: MarkedUpText::class)]
public MarkedUpText $body;                                      // body_source, body_format, body_html
```

In a Symfony application, `phil/markup-bundle` registers the services and the Twig filters.

## Requirements

PHP 8.5 or later with `ext-ctype`, `ext-dom`, `ext-filter`, `ext-libxml` and `ext-mbstring`, `phil/ujo`, `symfony/html-sanitizer` 8.2 and
`symfony/yaml` 8.2. Doctrine ORM 3.7 and DBAL 4.5 are optional: only an application that stores a `MarkedUpText` as
an embeddable needs them, and it requires them itself; the readers, the compiler, the purifier and `Markup::text()`
work without.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
