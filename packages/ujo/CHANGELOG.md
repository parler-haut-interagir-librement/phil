# Changelog

All notable changes to `phil/ujo` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Document` and `Block`, immutable values of a UJO (Universal JSON Output) document: `fromArray()` checks the
  structure, `toArray()` writes the JSON form, `walk()`, `find()` and `children()` reach nested blocks.
- `JsonDecoder`, strict reading under a size and a depth limit, and `JsonEncoder`, writing with the empty objects of
  `data`, `tunes`, `meta`, `file` and `settings` kept as objects.
- `Schema`: `Shape`, `Property` and the fields `Html`, `Plain`, `Integer`, `Boolean`, `Choice`, `Link`, `ListOf`,
  `Blocks`, `Lazy`, `Dictionary` (named texts, bounded in number and length) and `TextOrShape` (a text or an object),
  each refusing a value with its path; `Property::filled()`, a value filled in for publication; `Sized`, the fields
  whose values have a size — the characters a reader sees, the items, the blocks, the entries —, and `Bounded`, a
  sized field within bounds for publication.
- `BlockType`, with its `fold`, `upgrade`, `check` and `publication` hooks and its `maxPerDocument`, the most blocks
  of the type one document may hold, and `BlockTypeRegistry`, by name or alias; the default registry built once and
  shared.
- `StandardTypes`: every official Editor.js tool — paragraph, header, list with nested items and checklists, quote,
  warning, delimiter, code, raw, table, image, link tool, attaches, embed and personality —, reading list 1.x, the
  checklist tool, the nested checklist, the simple image, alignment and anchors saved in the data, a code block's
  language, and an image's own alternative text, `alt`, apart from its caption; the rich form of a table — cells
  `{text, colspan, rowspan, header, align}`, a `caption`, the alignment of each column — beside its grid of texts,
  `TableGrid` laying its spans out as a browser does; an embed's `poster`, `title` and `qrCode` for paper.
- `EmbedServices`, the services an embed may frame and the hosts of their frames.
- `CommunityTypes`: alert, toggle, columns, math — in LaTeX, in MathML Core, or both —, mermaid, chart, button and
  gallery, folding the flat toggle and collapsible blocks, and reading the calumk columns, LaTeX, `AnyButton` and the
  carousel.
- `MediaTypes`: `video` and `audio`, their sources in order, their text tracks with their language, their poster and
  options, and their fallback for paper.
- `LayoutTypes`: page and column breaks, page styles, `wide`, column flows, spacers, page numbers, running footers,
  footnotes, tables of contents and lists of abbreviations generated at render, ten of each per document, indexes,
  watermarks, notes, read-aloud text, stat blocks, definition lists, credits, banners, decorations, QR codes and
  containers.
- `StoredMarkup`: the optional `rendered` property of `math`, `mermaid`, `chart` and `qrCode`, `{markup, key}`, the
  drawing written once on the server, at most 1 MiB, its key of 1 to 256 URL-safe characters.
- `TuneType`, `TuneRegistry` (the default one built once and shared) and `Tunes`: alignment, anchor, text variant,
  notice, indent, layout, mask, table of contents and variant, with the names of their Editor.js tunes; `language`
  (a BCP 47 tag and a direction), `data` (the `data-*` values of a block, rendered only when asked) and `note` (a
  writer's note, never rendered).
- `Normalizer`, checking a document into canonical form: types and tunes by canonical name, flat children folded,
  older forms upgraded, defaults written, HTML sanitized, identifiers unique, nesting limited, blocks limited in
  number — 10,000 by default — and HTML fields in length — 100,000 characters, 50,000 for the `raw` profile —; a
  document it returned is returned again as it is, not checked a second time unless asked at a level it was not
  checked at.
- `Validation`, the two levels a document is checked at: `Shape`, the default, for every save, a draft included, and
  `Publication`, for publishing, scheduling and review, which also asks the values the types require filled in and
  the bounds they set; the standard types require a heading, a quote, code and a person's name filled in, a list's
  items and a table's rows.
- `InlineSanitizer`, `InlineProfile`, `InlineTool`, `AttributeValue`, `LanguageTag` and `SafeUrl`: inline HTML
  sanitized from scratch in time linear in its length, however deep its elements are nested, with the `rich`,
  `plain`, `mathml` and `raw` profiles and every inline tool of Editor.js and of the community, abbreviations,
  passages in another language (`<span lang dir>`, `<bdi>` and `<bdo>` read as one), ruby and formulas in MathML Core
  included; a tool reads its patterns once and names its tags, `InlineTool::tags()`, by which a profile finds the
  tools of an element, may be kept only `within` other elements and may need `anyOf` its attributes; links of the
  web, `mailto`, `tel`, `sms`, `geo` and the messengers XMPP, Matrix and Viber, each scheme of its own form.
- `HtmlRenderer`, `Rendering` — with `remember()`, a value computed once per rendering, by which a table of contents and
  a list of abbreviations read the document once —, `BlockRenderer` and the default renderers `TextRenderer`,
  `CodeRenderer`, `ListRenderer`, `MediaRenderer` and `LayoutRenderer`: one renderer per type, replaceable, with the
  tunes written as identifiers, classes and styles, and every HTML value sanitized again; code blocks with their
  `language-…` class, coloured by a highlighter given to `withCodeHighlighter()` whose HTML is kept only when it is the
  escaped code in spans; an image's `alt` its own, or its caption's text without one; `StoredMarkupRenderer`, added by
  `withStoredMarkup()`, which writes a block's stored SVG or MathML once a closure has proved its key and falls back to
  the block's own renderer otherwise; `HtmlFilter`, last passes over the rendered HTML added by `withFilter()`; `lang`
  and `dir` from the `language` tune, and the `data` tune as `data-*` attributes once `withDataAttributes()` asks for
  them; videos, sounds and frames followed by their fallback for paper, `.ujo-print-fallback`.
- `PlainText`, one conversion to text per type, replaceable; an image gives its caption, or its `alt` without one;
  `passages()`, the text by language, as the `language` tunes of the blocks say.
- `Ujo`, the library in one object: `decode()` and `normalize()` at a validation level, `encode()`, `toHtml()` and
  `toText()`.
- `UjoException`, implemented by `InvalidJson`, `InvalidDocument`, `UnknownBlockType` and `InvalidConfiguration`.
- The documentation in `doc/`, one file per topic, indexed by the README, with the extension points.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
