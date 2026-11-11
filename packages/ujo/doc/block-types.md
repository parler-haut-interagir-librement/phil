# Block types

Every type of UJO (Universal JSON Output) is named at the same level as those of the Editor.js tools, in camel case,
without prefix. "Also read" lists the other names and forms a type accepts; they are written back in the canonical form.
Values left out take the default shown, or stay out when there is none. HTML values are sanitized with the `rich`
profile unless said otherwise ([inline-html.md](inline-html.md)); plain values are kept as they are and escaped when
rendered.

## Official tools — `StandardTypes`

| Type | Data | Also read |
|---|---|---|
| `paragraph` | `text` html | `alignment` in the data, moved to the `alignment` tune |
| `header` | `text` html; `level` 1–6, default 2 | `alignment` and `anchor` in the data, moved to their tunes |
| `list` | `style` `unordered`, `ordered` or `checklist`; `meta` `{start ≥ 0, counterType}`; `items` `[{content html, meta {checked}, items […]}]` | list 1.x, items as texts; the `checklist` tool, `{items: [{text, checked}]}`; the nested checklist, items with `checked` |
| `quote` | `text` html; `caption` html, default `""`; `alignment` `left` or `center`, default `left` | |
| `warning` | `title` html; `message` html; both default `""` | |
| `delimiter` | optionally `style` `star`, `dash` or `line`; `lineWidth` 1–100; `lineThickness` 1–20 | |
| `code` | `code` plain; optionally `language`, up to 32 of `A-Za-z0-9_+#.-` | `showlinenumbers` of CodeFlask, dropped |
| `raw` | `html`, sanitized with the `raw` profile | not in the default registry |
| `table` | `withHeadings`, `stretched`, default `false`; `content` rows of cells, every row as wide as the first; in the rich form, a cell is `{text html, colspan 1–100, rowspan 1–1 000, header, align left, center or right}`, a cell spanning rows covering the columns below it, and the table has optionally a `caption` html and `align`, the alignment of each column, `""` for none | |
| `image` | `file` `{url, …}`, keeping what the server added; `caption` html; optionally `alt`, plain, up to 1 000 characters, the alternative text when it differs from the caption; `withBorder`, `withBackground`, `stretched`, default `false`; optionally `unsplash` `{author, profileLink}` | the simple image's `url`, moved into `file` |
| `linkTool` | `link`, `http` or `https`; `meta` `{title, site_name, description, image {url}}`, keeping what the server added | `link` |
| `attaches` | `file` `{url, size, name, extension}`, keeping what the server added; `title` plain, default `""` | |
| `embed` | `service`; `source`, `http` or `https`; `embed`, `https` on a host of the service; optionally `width`, `height` 1–10 000; `caption` html; optionally `poster`, `title` plain up to 200 characters and `qrCode`, what paper shows in place of the frame | |
| `personality` | `name` plain; `description` plain, default `""`; optionally `link`, `http` or `https`, and `photo` | |

An address is `http`, `https` or relative, never protocol-relative, unless the table says otherwise. The embed services
are those of the Editor.js embed tool, each with the hosts of its frames; `EmbedServices::with()` adds one, and
`StandardTypes::all($services)` or `StandardTypes::embed($services)` takes the result.

## Community tools — `CommunityTypes`

| Type | Data | Also read |
|---|---|---|
| `alert` | `type` `info`, `primary`, `secondary`, `success`, `warning`, `danger`, `light` or `dark`, default `info`; `align` `left`, `center` or `right`, default `left`; `text` html | `message`, as `text` |
| `toggle` | `text` html; `open`, default `false`; `blocks` | the toggle block, `{text, status, fk, items}`, and the collapsible block `accordion`, `{title, settings {blockCount, defaultExpanded}}`: the `items` or `blockCount` blocks after them are folded inside |
| `columns` | `cols`, 1 to 6 of `{blocks}` | the calumk columns, each a whole document `{time, blocks, version}` |
| `math` | `latex` plain, up to 20 000 characters, or `mathml`, one `<math>` element of MathML Core kept as the `mathml` profile allows, or both; optionally `rendered` | the LaTeX block `Math`, `{math}` |
| `mermaid` | `code` plain, up to 20 000 characters; `caption` html; optionally `rendered` | |
| `chart` | a Chart.js configuration, kept as it is; `rendered` checked when present | |
| `button` | `link`; `text` plain | `AnyButton` |
| `gallery` | `files`, at least one `{url, caption}`, keeping what the server added; optionally `source`; `style` `fit` or `slider`, default `fit` | the carousel, a list of `{url, caption}`, as a slider |

`rendered`, of `StoredMarkup::property()`, is the drawing a server keeps beside the source: `{"markup": svg or
mathml, "key": text}`, the markup at most 1 MiB, the key 1 to 256 letters, digits and `._~-`. The normalizer checks
only its shape: the markup is written in a page only once `HtmlRenderer::withStoredMarkup()` has proved its key —
see [HTML rendering](html-rendering.md#stored-drawings). `phil/svg-blocks` writes it.

## Players — `MediaTypes`

| Type | Data |
|---|---|
| `video` | `sources`, 1 to 8 `{url, type}` in the order a player tries them, `type` a media type such as `video/webm; codecs="vp9"`; `tracks`, up to 16 `{url, kind, language, label, default}`, `kind` `captions`, `subtitles`, `descriptions`, `chapters` or `metadata`, default `subtitles`, `language` a BCP 47 tag; `controls`, default `true`; `loop`, `muted`, default `false`; optionally `poster`, `width`, `height` 1–10 000 and `title`; `caption` html; optionally `qrCode` |
| `audio` | the same, without `poster`, `width` and `height` |

A source may be a file of the media library, by its address. On paper, a player shows its poster, its title and its
first source's address, and a QR code of that address when `qrCode` is true.

The `layout` tool of `editorjs-layout` is not read: its containers carry free CSS, which no type here accepts. A site
that needs it registers its own type.

## Page and game types — `LayoutTypes`

| Type | Data |
|---|---|
| `pageBreak`, `columnBreak` | none |
| `pageStyle` | optionally `variant` `frontCover`, `insideCover`, `partCover` or `backCover`; `classes`, names, default `[]` |
| `wide` | `blocks`, spanning every column of the page |
| `columnFlow` | `count` 2–6, default 2; `blocks`, flowing in columns |
| `spacer` | `lines` 1–50, default 1 |
| `pageNumber` | `mode` `auto`, `fixed`, `skip` or `reset`, default `auto`; optionally `value`, up to 16 characters |
| `runningFooter` | `text` html: the label at the foot of the pages that follow |
| `footnote` | `id`, a name; `text` html: the note `<sup data-footnote="id">` refers to |
| `toc` | `title` plain; `depth` 1–6, default 3; `wide`, default `false`: generated from the headings when rendered; at most ten per document |
| `index` | `title` plain; `columns` 1–6, default 2; `entries` `[{term, pages, children […]}]` |
| `abbreviations` | `title` plain: generated when rendered, each `<abbr title>` of the document once, with the first meaning it is given, in alphabetical order; at most ten per document |
| `watermark` | `text` plain, up to 100 characters |
| `note`, `descriptive` | `title` html, default `""`; `blocks`: a boxed note, read-aloud text |
| `statblock` | `kind` `monster`, `spell`, `item` or `other`; `name`; `subtitle` html; `frame`, `wide`; `sections` `[{title, properties [{label, value html}], abilities [{label, score, modifier}], blocks}]`; optionally `record`, the game data it is bound to, or `null` |
| `definitionList` | `items`, at least one `{term html, definitions [html, …]}` with at least one definition |
| `credit` | `title` plain; `text` html |
| `banner` | `text` plain, up to 100 characters |
| `decoration` | `kind` `stain` or `ornament`; `variant` 1–99; optionally `color` `#rrggbb` |
| `qrCode` | `data`, 1 to 2 048 characters; optionally `rendered` |
| `container` | `classes`, at least one name the look declares; `blocks` |

A stat block holds no game system: its labels and values are the writer's.

## Tunes — `Tunes`

| Tune | Value | Also read |
|---|---|---|
| `alignment` | `{alignment: left, center, right or justify}` | `alignmentTune` |
| `anchor` | `{anchor}`, a letter then up to 63 of `A-Za-z0-9_-` | `anchorTune` |
| `textVariant` | `""`, `call-out`, `citation` or `details` | |
| `notice` | `{style: info, warning or spoiler, caption}` | `noticeTune` |
| `indent` | `{indentLevel: 0–8}` | `indentTune` |
| `layout` | `{position: absolute or relative, top, right, bottom, left, width, height, wrap: left or right, zIndex: -10–100}`, lengths as `20px`, `-3cm`, `1.5em`, `50%`, `0` or `auto` | |
| `mask` | `{shape: center, edge or corner, variant 1–99, offsetX, offsetY: percentages, rotation: -360–360}` | |
| `toc` | `{include}`: whether a heading enters the table of contents | |
| `variant` | `{name, frame, decoration}`: a variant the look declares | |
| `language` | `{language, direction}`: a BCP 47 tag and, optionally, `ltr`, `rtl` or `auto`, for a block in another language than the document | |
| `data` | up to 32 texts of up to 1 000 characters, under names `[a-z][a-z0-9-]*` of up to 64 characters: the `data-*` values of the block, which a site binds to its own fields | |
| `note` | `{text}`, up to 10 000 characters: the writer's note on the block, never published | |
