# Testing

| Area | What is covered |
|---|---|
| `Reader\Markdown\MarkdownReader` | Each construct of CommonMark and of the GitHub extensions, footnotes, attributes, definition lists, anchors, `[TOC]`, front matter, `:::` blocks, the page syntax, raw HTML kept and escaped; each source compared to the HTML its UJO document renders |
| `{{ }}` blocks | The spans that are blocks, attributes on blocks, stat blocks, boxes, covers, quotes, credits, class tables, tables of contents |
| `Reader\Markdown\BaseDialect` and the points of a dialect | A dialect that changes nothing reads as plain Markdown; a dialect of each point — inline and block rules, a syntax turned off, metadata, definitions, the mark of a definition and of a paragraph — reads its syntax, and keeps nothing between two readings |
| `Adapter\DefaultCompiler` and the formats of add-ons | A format read by its provider at the level asked, a format no reader knows, the names an add-on cannot take |
| `Lexer\Cursor`, `Lexer\Slugger`, the rules | Through the readers, each branch by a source written for it, and on their own |
| `Reader\UjoReader` | A valid document, JSON that is not JSON, a block refused |
| `Reader\BBCode\BBCodeReader` | Every tag and extension and its edge cases, options and attributes, sources not well formed, new lines and blank lines, links and images refused, nesting deeper than UJO allows, raw HTML kept and escaped, metadata |
| `Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader`, `Reader\Creole\CreoleReader` | The native syntax of each wiki and the shared extension tags, each source compared to the HTML its UJO document renders; marks, links, templates and containers left open or crossing, nesting deeper than UJO allows, raw HTML kept and escaped, metadata, the paths of pages and media |
| `Reader\Html\HtmlReader` | Every element and the classes of `phil/ujo`, the styles of a paste, elements left out, nesting deeper than UJO allows and deeper than is read, the metadata of a page |
| `WikiLinearityTest` | Sources of 100 KB written to be slow, for BBCode, each wiki and HTML, each read at an eighth and at half of its size, the processor time held under the growth a quadratic reading would reach; these tests attribute no coverage, so that mutation testing does not replay them for every mutant |
| `Adapter\Autolink` | Addresses made links, escaping |
| `Adapter\DefaultCompiler`, `Adapter\PlainText` | Every format, raw HTML by level, the readers it gives, paragraphs and line breaks |
| `Reader\Text\TextReader` | Paragraphs, line breaks, escaping and links as UJO blocks, the same text as `PlainText` once rendered |
| `Adapter\SanitizerPurifier` | What each level keeps and removes, schemes of links and images, the hosts images come from, a long input |
| `Markup`, `MarkedUpText` | Compiling and purifying, with a compiler that knows levels and one that does not; the embeddable written and read back by Doctrine on SQLite |

```sh
vendor/bin/phpunit packages/markup/tests
```

The source files are held to 100 % of mutants killed by Infection, run from the monorepo root.

## Benchmarks

`BBCodeBench` reads forum posts, then sources written to be slow — tags nested deep, closing tags without an opening
one, brackets that open no tag, items, rows and cells left open, headings and footnotes by the thousand —, at 25, 50
and 100 KB; the time doubles with the size. `ReadersBench` reads a small document in each format, the same document
repeated to 10 and 100 KB, and 10 and 100 KB of delimiters left open; the time grows ten times from 10 to 100 KB. From
the monorepo root:

```sh
composer bench -- packages/markup/tests/Benchmark
```

In worker mode, `tests/Worker/worker.php` is a page FrankenPHP serves from one process across requests, reading a Markdown document of 10 KB into purified HTML; the
services it builds before its loop serve every request, as they do in an application's worker:

```sh
composer bench:worker phil/markup
```

## Fuzzing

The fuzz targets `markup-markdown`, `markup-bbcode`, `markup-creole`, `markup-dokuwiki`,
`markup-mediawiki` and `markup-html` give each reader inputs nobody wrote, from the seeds of `tests/Fuzz/corpus/`:
a reader of a written format never throws, whatever it reads. From the monorepo root:

```sh
composer fuzz -- markup-markdown markup-html
```

## Testing code that uses the library

Compare HTML rather than UJO when what a page shows is what matters: `HtmlRenderer::default()->render($document)`
gives one line per block. Block identifiers are given in reading order, `b1`, `b2`…, so a document read twice from
the same source is the same.
