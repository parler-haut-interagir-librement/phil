# phil/ujo

**UJO (Universal JSON Output) documents as values you can trust: read strictly, checked block by block, rendered as safe HTML.**

phil/ujo is the model of UJO (Universal JSON Output), a JSON format for documents made of blocks — a time, a version and
an ordered list of blocks, each with an identifier, a type, its data and its tunes. It reads that JSON strictly into
immutable values, checks every block against the shape of its type, upgrades the older and third-party forms of the data
to one canonical form, sanitizes the inline HTML each text may hold, and renders the document as HTML or as plain text.
It knows every official Editor.js tool, the community tools worth reading — alerts, toggles, columns, formulas,
diagrams, charts, buttons, galleries —, and the types of printed pages and game books: page and column breaks,
footnotes, tables of contents, indexes, boxed notes, stat blocks. It does not edit documents, convert other markup to
UJO, or draw formulas, diagrams and QR codes on the server.

It is for any PHP application that stores what its writers compose in a block editor and shows it to readers: a
CMS, a wiki, a forum, a tool that lays out books. A document is checked once, when it is received, and stored in
canonical form; rendering it again checks it again, so a tightened rule reaches older documents. In a Symfony
application, `phil/ujo-bundle` adds the `ujo_html` and `ujo_text` Twig filters, a form field holding UJO JSON and a
validation constraint.

It is a plain PHP library, written from scratch, without dependency. The data written by the tools of Editor.js editors
is read as it is shaped; no line of those tools is copied. Its HTML sanitizer is its own: it keeps, field by field, the
inline elements a profile allows, written back in one canonical form, and nothing else.

The design is small: `Document` and `Block` are the values; `BlockTypeRegistry` holds a `BlockType` per type, whose
`Shape` of fields checks the data; `Normalizer` applies them; `HtmlRenderer` renders with one `BlockRenderer` per type;
`PlainText` converts; `Ujo` puts them together.

## Status

| Part | State |
|---|---|
| `Document`, `Block`: values, structure checked, nested blocks found, JSON form | Implemented and tested |
| `JsonDecoder`, `JsonEncoder`: strict reading with size and depth limits, writing in canonical form | Implemented and tested |
| Block types: 13 official, 8 community, 21 page and game types; `raw` on request | Implemented and tested |
| Older and third-party forms: lists 1.x, checklists, nested checklists, simple images, flat toggles and collapsible blocks, calumk columns, LaTeX, buttons, carousels, alignment and anchors saved in the data | Implemented and tested |
| Two validation levels: the shape for every save, required values filled in and bounds for publication | Implemented and tested |
| Tunes: alignment, anchor, text variant, notice, indent, layout, mask, table of contents, variant | Implemented and tested |
| `InlineSanitizer`: `rich`, `plain` and `raw` profiles of inline tools | Implemented and tested |
| `HtmlRenderer`: one renderer per type, replaceable; tunes as classes, identifiers and styles | Implemented and tested |
| `PlainText`: one conversion per type, replaceable | Implemented and tested |
| The `layout` community tool (free CSS layouts) | Not read, by design |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Documents](doc/documents.md) | `Document` and `Block`, the JSON form, strict reading and writing, nested blocks. |
| [Block types](doc/block-types.md) | Every type, its data, the forms it also reads, and the tunes. |
| [Normalization](doc/normalization.md) | What `Normalizer` checks and changes, registries, and adding a type. |
| [Inline HTML](doc/inline-html.md) | The sanitizer, its profiles and inline tools, and why it is the library's own. |
| [HTML rendering](doc/html-rendering.md) | The HTML of each type, the tunes, replacing a renderer, and purification. |
| [Plain text](doc/plain-text.md) | The text of each type, and replacing a conversion. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Exceptions](doc/exceptions.md) | `UjoException` and its four exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that stores documents. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/ujo
```

```php
use Phil\Ujo\Ujo;

$ujo = new Ujo();

$document = $ujo->decode($json);          // strict, checked, canonical — or an exception naming the path
$stored = $ujo->encode($document);        // the canonical JSON to keep

echo $ujo->toHtml($stored);               // <h2 id="…" class="ujo-header">…</h2>…
echo $ujo->toText($stored);               // for a search index or an excerpt
```

## Requirements

PHP 8.5 or later, with `ext-json` and `ext-mbstring`. Nothing else.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
