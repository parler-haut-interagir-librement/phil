# phil/epub

**EPUB 3 books from the HTML you already have, valid for EPUBCheck and readable by assistive technologies.**

phil/epub writes EPUB 3 publications. It takes chapters as fragments of purified HTML, turns them into well-formed
XHTML content documents, and packages them with their stylesheets, images and fonts: the package document with its
metadata — title, language, identifiers, creators, modification time — and its schema.org accessibility metadata, the
navigation document with its table of contents, landmarks and page list, the EPUB 2 table of contents when asked, the
manifest and the spine, a cover, audio and video files with their fallbacks and text tracks, media overlays that read
the text aloud in sync, fixed-layout pages, fonts obfuscated with the IDPF algorithm when asked, and the ZIP container
with its `mimetype` first and stored. Links that
cross chapters are rewritten to the chapter that holds their target, and addresses the book does not hold are dropped
rather than left broken.

It is for any PHP application that publishes long texts and wants them read offline, in a reading application or on an
e-reader: a CMS exporting a series of articles, a tool that lays out game books, a wiki exporting a section, a
newsletter archive. A book is written in one call, in memory or to a file, and the same book gives the same bytes. An
application that stores documents in UJO (Universal JSON Output), the block document format of `phil/ujo`, turns them
into chapters through `phil/ujo`.

It is a plain PHP library that needs PHP's `dom` and `zip` extensions and nothing else;
`phil/ujo` is an optional dependency. The EPUB 3.3 specification, EPUB Accessibility 1.1 and the IDPF font
obfuscation algorithm are its references; no code of another EPUB library is used.

The design is small: a `Book` holds its `Metadata` (with an `Accessibility` value), its `Chapter` list — each with its
`MediaOverlay` when narrated —, its `Asset` files, its `Cover` and its `Rendition`; `EpubWriter` writes it.
`HtmlSplitter` cuts a long fragment into chapters at its headings, and `Ujo\UjoChapters` does the same with a UJO
document, its page breaks becoming the page list.

## Status

| Part | State |
|---|---|
| Container, `mimetype` first and stored, entries dated by the modification time, reproducible bytes | Implemented and tested |
| Package document: title, language, identifiers (UUID, ISBN-13, DOI, URN, own), creators and contributors with MARC roles, publisher, description, date, subjects, rights, source, modification time, direction | Implemented and tested |
| Accessibility metadata: access modes, sufficient modes, features, hazards, summary, conformance, certifier, with what the writer knows added | Implemented and tested |
| Navigation document: table of contents from the headings or given, landmarks, page list | Implemented and tested |
| EPUB 2 table of contents (`toc.ncx`) with its page list, for older reading systems | Implemented and tested |
| XHTML from purified HTML: parsed as HTML5, written as XML, SVG and MathML in their namespace, scripts and handlers removed, identifiers deduplicated | Implemented and tested |
| Links across chapters, resources checked against the book, addresses mapped by the application, remote audio and video | Implemented and tested |
| Stylesheets, images and fonts in EPUB core media types, read from their bytes; font obfuscation; cover | Implemented and tested |
| Audio and video files inside the book, read from their bytes, with manifest fallbacks; WebVTT text tracks | Implemented and tested |
| Media overlays: SMIL documents, durations, narrators and active classes in the package document | Implemented and tested |
| Fixed layout: pre-paginated books or chapters, viewport per page, orientation, spread and page spread | Implemented and tested |
| Chapters split at headings; UJO documents through `phil/ujo` with page breaks as page list | Implemented and tested |
| Output checked by EPUBCheck 5.4 | Tested when EPUBCheck is installed |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Books](doc/books.md) | `Book`, `Metadata`, identifiers, creators, chapters, assets, the cover and font obfuscation. |
| [Accessibility](doc/accessibility.md) | The accessibility metadata, what the writer adds, and what makes the content accessible. |
| [Content documents](doc/content.md) | How HTML becomes XHTML, links and resources, mapping addresses, and splitting a fragment into chapters. |
| [Navigation](doc/navigation.md) | The table of contents, the landmarks, the page list, their labels and the EPUB 2 table of contents. |
| [Media, overlays and layout](doc/media.md) | Audio, video and text tracks, fallbacks, media overlays, and fixed layout. |
| [UJO documents](doc/ujo.md) | `UjoChapters`: a UJO document rendered by `phil/ujo` and split into chapters with its pages. |
| [Exceptions](doc/exceptions.md) | `EpubException` and its two exceptions. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests, EPUBCheck, the mutation bar, and how to test code that writes books. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/epub
```

```php
use Phil\Epub\Asset;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Creator;
use Phil\Epub\EpubWriter;
use Phil\Epub\Identifier;
use Phil\Epub\Metadata;

$book = new Book(new Metadata(
    'The Lighthouse Keeper',
    'en',
    Identifier::uuid('7c9e6679-7425-40de-944b-e07fc1f90ae7'),
    new DateTimeImmutable(),
    creators: [new Creator('Ann Writer', 'aut')],
))
    ->withAsset(Asset::stylesheet('css/book.css', 'body { line-height: 1.5; }'))
    ->withChapter(
        new Chapter('<h1>The Storm</h1><p>The light went out at midnight.</p>'),
        new Chapter('<h1>The Morning</h1><p>See <a href="#the-boat">the boat</a>.</p><h2 id="the-boat">The Boat</h2>'),
    );

new EpubWriter()->write($book, 'lighthouse.epub');
```

## Requirements

PHP 8.5 or later, with the `dom` and `zip` extensions. `phil/ujo` for UJO documents.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
