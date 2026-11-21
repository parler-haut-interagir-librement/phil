# Changelog

All notable changes to `phil/markup` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.1-alpha - 2026-11-20

A Markdown source holding more than 100,000 lines, read before, is refused by
default. A patch version and not a minor one: a source of 1 MiB at most, the default ceiling, holds that many lines
only when most of them are empty.

### Added

- `Exception\InputTooComplex`, thrown by `MarkdownReader` for a source holding more lines than `maxLines`, counted
  before a list of its lines is built, whatever ends them; `$budget` names the budget, `lines`, and `$limit` its value.
- `maxLines` on `MarkdownReader`; `MarkdownReader::DEFAULT_MAX_LINES`, 100,000.
- The section [The budget of lines of Markdown](doc/analyser.md#the-budget-of-lines-of-markdown).
- Purifier corpus entries for character references padded with zeros and special schemes without their authority, and
  a test of images limited to some hosts in every address: `srcset` on `img` and `source`, a scheme without `//`, an
  address without its scheme, a host that only starts like an allowed one.

### Changed

- The fuzz target `markup-markdown` reads under a budget of 64 lines and checks that a source is refused exactly when
  it holds more; the seed `many-lines.md` mixes the three ends of a line.

## 0.3.0-alpha - 2026-11-18

A source longer than 1 MiB, or a UJO source longer than 4 MiB, read before, is
refused by default, so is an HTML longer than 4 MiB given to a purifier, and an HTML comment alone in Markdown is a
note instead of text.

### Added

- `Exception\InputTooLarge`, thrown by every reader and by `Adapter\DefaultCompiler` for a source longer than its
  ceiling, before a byte of it is read; `$bytes` and `$limit` say both lengths, and `check()` refuses an input above a
  ceiling.
- `maxBytes`, the ceiling of a source in bytes, on `MarkdownReader`, `BBCodeReader`, `MediaWikiReader`,
  `DokuWikiReader`, `CreoleReader`, `HtmlReader`, `TextReader`, `UjoReader` and `DefaultCompiler`, which gives it to
  the readers `reader()` returns and puts the reader of an add-on's format behind it; `MarkupReader::DEFAULT_MAX_BYTES`,
  1 MiB.
- `UjoReader::DEFAULT_MAX_BYTES`, the ceiling of a UJO source, that of `phil/ujo`'s `JsonDecoder`: 4 MiB.
  `ujoMaxBytes` on `DefaultCompiler`, the ceiling of a UJO source compiled or read, given to its reader of UJO and to
  the decoder behind it.
- `maxBytes`, the ceiling of an HTML purified in bytes, on `SanitizerPurifier` and its `defaultPolicies()`,
  `AmmoniaPurifier::defaultPolicies()` and `Purifiers::defaultPolicies()`: a longer HTML is refused whole by
  `InputTooLarge` before a sanitizer sees it; `HtmlPurifier::DEFAULT_MAX_BYTES`, 4 MiB. The fuzz target
  `markup-purifier` purifies under a ceiling of 2 KiB and checks that a longer input is refused.
- The Markdown reader keeps an HTML block of comments alone as the `note` tune of the block that follows it, or of the
  last block when none follows, as the HTML reader keeps the comments of a page: the speaker notes of a Marp deck are
  read as they are written.
- `testMarkdownIsReadInLinearTime()`, and the shapes of sibling HTML elements left out or whose `data-*` is not kept,
  in `WikiLinearityTest`; 100 KB of short Markdown paragraphs and of sibling HTML elements left out in
  `ReadersBench`; fuzz seeds of a Marp deck and of sibling losses.
- The section [The ceiling of a source](doc/analyser.md#the-ceiling-of-a-source), the section
  [The ceiling of an HTML purified](doc/reference.md#the-ceiling-of-an-html-purified) and the section
  [Comments](doc/markdown.md#comments) of the Markdown page.

### Changed

- Every reader, `DefaultCompiler` in every format — HTML and plain text included —, and so `Markup`, refuse a source
  longer than 1 MiB unless configured; a UJO source, longer than 4 MiB.
- `SanitizerPurifier`, `AmmoniaPurifier`, and so `Markup`, refuse an HTML longer than 4 MiB unless configured, where
  they purified any length before.
- An HTML comment alone in Markdown is no longer escaped as text below the level `all`, nor dropped at `all`: it is
  the note of a block.

### Fixed

- The Markdown reader appends each block in place: a document of many blocks, of many definition lists that follow
  each other, a list item holding many lists and a section of a stat block holding many blocks took a time that grew
  with the square of their number.
- The HTML reader counts the children of an element once for the paths of its losses: a page of many sibling elements
  left out took a time that grew with the square of their number.
- `ReadersBench` reads its sources written to be slow without the limits of a stored document, which their paragraphs
  of 100 KB broke.

## 0.2.1-alpha - 2026-11-15

### Added

- `Adapter\AmmoniaPurifier`: the policies of `SanitizerPurifier::defaultPolicies()` on the `ammonia` extension of PHP,
  for PHP 8.2 to 8.5, about twice as fast as the sanitizer from 12 KB and with a seventh of its memory on 100 KB; the
  same elements kept at each level, `style` and the addresses the extension does not check removed, classes filtered as
  `CodeClasses` filters them. A short HTML, an HTML of more than 10,000 tags, one holding `<noscript`, one that is not
  valid UTF-8, and images of limited hosts go to the sanitizer. `isAvailable()` tells whether the extension is loaded;
  `ext-ammonia` in `suggest`.
- `Adapter\Purifiers::defaultPolicies()`: `AmmoniaPurifier` where the extension is loaded, `SanitizerPurifier`
  otherwise, or always the sanitizer.
- `SanitizerPurifier::configurations()`, the configuration of each level the default policies are built from;
  `CodeClasses::keep()`, the classes kept of a `class` attribute.
- `Exception\MissingExtension`, thrown by an adapter whose extension is not loaded.
- `PurifierCorpusTest`, hostile HTML given to both purifiers at each level; the fuzz target `markup-purifier`; the
  benchmark `PurifierBench`, the purification alone; the worker page purifies with `Purifiers`.
- The page [Accelerators](doc/accelerators.md).
- `Adapter\CheckboxText`, the filter `DefaultCompiler` runs below the level `all`: the checkbox of a checklist item
  written as text, `☑` checked and `☐` not, so that the state is still read where no `input` is kept.
- `LossReason::MisplacedCheckbox`, a checkbox in a list item that is not at its start; `LossReason::PartialChecklist`,
  a checkbox at the start of an item in a list some items of which say no state; `LossReason::TableInListItem`, a
  table in a list item, which UJO has no place for.
- The section [Checklists](doc/reference.md#checklists) of the reference: what each level keeps of a checklist.

### Changed

- Bold and italic in Markdown — `**b**`, `__b__`, `*i*`, `_i_` — are `<strong>` and `<em>`, as CommonMark writes
  them, `***x***` being `<em><strong>x</strong></em>`; so are `'''bold'''` and `''italic''` in MediaWiki, and
  `**bold**` and `//italic//` in DokuWiki and Creole. BBCode's `[b]` and `[i]`, and the tags `<b>` and `<i>` of HTML,
  stay `<b>` and `<i>`.
- The level `all` keeps the checkbox of a checklist item: `SanitizerPurifier` writes each `input` again as
  `<input type="checkbox" disabled>`, `checked` when it had it, and removes an input of any other type or without a
  type. Below `all`, `label` is unwrapped, its text kept.
- `AmmoniaPurifier` gives an HTML that holds `<input` to the sanitizer.
- The HTML reader takes the state of a checklist item from the checkbox at its start only — a child of the `<li>`, or
  the first child of a `<label>` or a `<p>` it starts with —, and takes that checkbox out of the item's content.
- `phil/ujo` 0.1.2 or later is required, for the accessible checkboxes of its checklists.

## 0.2.0-alpha - 2026-11-14

### Fixed

- The HTML reader, and the HTML tags read in wikitext, keep `<strong>` and `<em>` as `<strong>` and `<em>` in UJO,
  instead of `<b>` and `<i>`.

### Changed

- The level `all` follows the safe elements of Symfony 8.2.0-BETA2's HtmlSanitizer: `link`, `meta`, `plaintext`,
  `html`, `body` and the attribute `referrerpolicy` are no longer kept.
- `phil/ujo` 0.1.1 or later is required, so that `<strong>` and `<em>` are kept from the reader to the HTML rendered.

## 0.1.0-alpha - 2026-11-11

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
- `Reader\Markdown\MarkdownReader`, reading in two passes, blocks then inline content by one rule per
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
- `Reader\BBCode\BBCodeReader`, on the same analyser, in linear time however the tags are nested,
  left open or closed without being opened: the tags of forums — bold, italic, underline, strikethrough, exponents,
  highlight, inline code, links, emails, images with their alternative text and their layout, quotes with their
  author, code with its language, lists ordered, counted or checked, tables with `colspan`, `rowspan` and `align`,
  headings, colors, alignment, spoilers, `[ruby=reading]` —
  and extensions in their style for every feature of the Markdown reader — `[footnote]`, `[dl]`, `[toc]`, attributes
  `id`, `class`, `align`, `width`, `height`, `wrap`, `[note]`, `[descriptive]`, `[warning]`, `[alert]`, `[wide]`,
  `[block]`, `[columns]` and `[column]`, `[pagebreak]`, `[columnbreak]`, `[statblock]`, `[credit]`, `[classtable]`,
  `[spacer]`, `[pagenumber]`, `[footer]`, `[watermark]`, `[banner]`, `[decoration]`, `[nbsp]`, `[icon]`, `[hspace]`,
  `[var]`, `[metadata]`, `[style]` —; raw HTML in `[html]` kept for `all` or escaped below.
- `Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader` and `Reader\Creole\CreoleReader`, built on one
  reader the three wikis share, `Reader\Wiki`, in linear time however marks, links, templates and
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
