# Changelog

All notable changes to `phil/markup` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `MarkupFormat` (`markdown`, `html`, `bbcode`, `mediawiki`, `dokuwiki`, `creole`, `text`, `ujo`), `nameOf()` the name of
  a case or of a format an add-on brings, and `Level` (`basic`, `forum`, `all`).
- `MarkedUpText`: a source, the name of its format and its purified HTML, a Doctrine embeddable; `doctrine/dbal` and
  `doctrine/orm` optional, in `suggest`, only an application that stores it needing them.
- `Exception\MarkupException`; `Exception\InvalidSource`, thrown for a UJO source that is not valid;
  `Exception\UnknownFormat`, for a format no reader knows and a name an add-on cannot take.
- `MarkupCompiler` and `HtmlPurifier`, the two ports, a format given as a `MarkupFormat` or by its name; `LevelAwareCompiler`, a compiler told the level its HTML will
  be purified at; `Markup`, which compiles — at the level, for a compiler that knows levels — then purifies.
- `Lexer\Cursor`, the analyser every reader shares: a cursor that lexes forward without regular expressions —
  `comesNext()`, `consume()`, `consumeWhile()`, `consumeUntil()`, `consumeThrough()` —, and moves back when a rule
  does not match; `Lexer\Slugger`, the unique anchors of a document's headings, in constant time per heading.
- `Reader\MarkupReader` and `Reader\Reading`: a source read into a checked UJO document of `phil/ujo`, with its
  metadata and its losses, `Reader\Loss` and `Reader\LossReason` — the element not kept, its path and why —; the
  readers given no normalizer share one, built once, that accepts `raw` blocks.
- `Contract\ExtractedElementStore`, the port where a reader stores, as files, the elements it extracts: an inline SVG drawing.
- `Reader\Markdown\MarkdownReader`, written from scratch in two passes, blocks then inline content by one rule per
  token: CommonMark — headings, paragraphs, quotes, lists, code, rules, links and images, references, emphasis,
  escapes, entities, HTML —, the text of an image its alternative text and its title, written after its address or
  in its reference, its caption; the GitHub extensions — tables, strikethrough, autolinks, task lists —; footnotes;
  attributes `{.class #id}` and `{class,#id,property:value}` as tunes or a container; definition lists; heading
  anchors; `[TOC]`; `==highlight==`; ruby, `{漢字|かんじ}`; YAML front matter; `:::` blocks; raw HTML kept for `all`
  or escaped below; pages and columns, `{{ }}` blocks and the spans alone on their line mapped to the UJO types of the same role — boxed notes,
  read-aloud text, stat blocks, tables of contents, quotes, credits, class tables, spell lists, covers, page numbers,
  running footers, watermarks, banners, decorations. Any text is a document, read in linear time: emphasis is paired
  on a linked list of nodes and a paragraph of many lines is built in place. A `Reader\Markdown\Contract\MarkdownDialect`
  — `BaseDialect` to extend — adds its inline rules (`Rule\InlineRule`, `Rule\DelimiterRule('^')` for exponents) and
  block rules (`Contract\BlockRule`) after the built-in ones, turns off a built-in syntax (`MarkdownSyntax`), and reads
  its own metadata, `[label]: value` lines, definition marks and paragraph marks; what it keeps during one reading
  goes into `Context::$extra`.
- `Reader\BBCode\BBCodeReader`, written from scratch on the same analyser, in linear time however the tags are nested,
  left open or closed without being opened: the tags of forums — bold, italic, underline, strikethrough, exponents,
  highlight, inline code, links, emails, images with their alternative text and their layout, quotes with their
  author, code with its language, lists ordered, counted or checked, tables with `colspan`, `rowspan` and `align`,
  headings, colors, alignment, spoilers, `[ruby=reading]` —
  and extensions in their style for every feature of the Markdown reader — `[footnote]`, `[dl]`, `[toc]`, attributes
  `id`, `class`, `align`, `width`, `height`, `wrap`, `[note]`, `[descriptive]`, `[warning]`, `[alert]`, `[wide]`,
  `[block]`, `[columns]` and `[column]`, `[pagebreak]`, `[columnbreak]`, `[statblock]`, `[credit]`, `[classtable]`,
  `[spacer]`, `[pagenumber]`, `[footer]`, `[watermark]`, `[banner]`, `[decoration]`, `[nbsp]`, `[icon]`, `[hspace]`,
  `[var]`, `[metadata]`, `[style]` —; raw HTML in `[html]` kept for `all` or escaped below.
- `Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader` and `Reader\Creole\CreoleReader`, written from
  scratch on one reader the three wikis share, `Reader\Wiki`, in linear time however marks, links, templates and
  containers are left open, crossed or nested: the native syntax of each wiki — headings, emphasis, links to pages and
  to other sites, images and files placed and sized, with their caption — and MediaWiki's `alt=` apart from it,
  Creole's text as the alternative text —, lists, task lists, definitions, indents, tables — MediaWiki's caption,
  spans and alignment, DokuWiki's `||` and `:::` spans and alignment by spaces —, preformatted text, text
  kept as written, line breaks, rules; MediaWiki's entities, magic words, comments and templates, DokuWiki's footnotes
  `((…))`, media placed by their spaces and macros `~~…~~`, Creole 1.0 and its additions — and extension tags they
  share, `<note>` in MediaWiki and DokuWiki and `<<note>>` in Creole, for every feature of the Markdown reader:
  footnotes, `<ref name>` referenced again, spoilers, colors, icons, spaces, variables, notes, read-aloud text,
  warnings, alerts and DokuWiki's note plugin, quotes with their author, toggles, blocks across the columns, classes
  of the look, columns side by side or flowing, stat blocks, credits, class tables, alignment, running footers,
  watermarks, banners, tables of contents, page and column breaks, page styles, spacers, page numbers, decorations,
  code with its language, metadata and style, attributes `id`, `class`, `align`, `width`, `height`, `wrap`; raw HTML
  in `<html>` kept for `all` or escaped below. `Reader\Wiki\WikiLinks` says where the pages and the media a wiki text
  names are found.
- `Reader\Html\HtmlReader`, HTML read into UJO through the HTML5 parser of PHP: paragraphs, headings, lists and
  checklists, quotes, figures, images — their `alt` the alternative text, their `<figcaption>` or else their `title`
  the caption —, code, rules, tables, definition lists, details, alignment and the inline elements UJO keeps; the HTML
  `phil/ujo` renders, its `ujo-*` classes and footnotes read back into their blocks; the styles a paste from a word
  processor writes read as bold, italic, underline and strikethrough; frames of known services as `embed` blocks,
  videos and sounds with their tracks, formulas in MathML, download links alone in their paragraph as attached files,
  abbreviations, languages as tunes and `<span lang>`, ruby, tables with their caption, spans, header cells and
  alignments, a cell's lists as lines with their marks, `data-*` attributes and comments as the `data` and `note`
  tunes, links of the schemes `phil/ujo` allows and anchors the page names, inline SVG drawings purified by
  `phil/svg-sanitizer` and stored by an `ExtractedElementStore`; scripts, styles and forms left out; what is not kept
  listed in the reading's losses; the title, the language and the named `<meta>` of a page as its metadata;
  `ext-dom` and `ext-libxml` required.
- `Reader\UjoReader`: UJO JSON read strictly by `phil/ujo`.
- `Reader\Text\TextReader`: plain text read into UJO, a paragraph for each run of lines, line breaks kept, every
  character escaped and the addresses made links.
- `Adapter\DefaultCompiler`: Markdown, BBCode, MediaWiki, DokuWiki, Creole and UJO read into UJO and rendered by
  `phil/ujo`; HTML as written; plain text by `Adapter\PlainText`, addresses made links by `Adapter\Autolink`;
  `reader()` gives the reader of every format into UJO, HTML's and plain text's included; the formats of add-ons, each
  a `Reader\Contract\ReaderProvider` given by name, read and rendered the same way; `formats()` the name of each.
- `Adapter\SanitizerPurifier`: a policy of `symfony/html-sanitizer` per level, taking the hosts images may come from,
  since an image of another site, which every reader's browser would call, is tracking at the `forum` and `all`
  levels; every class dropped but those of code, kept by `Adapter\CodeClasses`: `language-…` on `code` and `hl-…` on
  `span`; links of the schemes of `phil/ujo`, and MathML Core at `all`.
- The documentation in `doc/`, indexed by the README, with the correspondence of the features of every format.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
