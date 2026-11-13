# Books

A `Book` is a value: its metadata, its chapters in reading order, its other files, its cover, and how its navigation
is made. Each `with` method returns a new book.

```php
$book = new Book($metadata)
    ->withChapter($titlePage, $chapterOne, $chapterTwo)
    ->withAsset($stylesheet, $map, $font)
    ->withCover(new Cover($coverImage, 'A lighthouse in a storm, under the title'));
```

## Metadata

`Metadata` holds what EPUB requires — a title, a language, a unique identifier and the moment the book was last
modified — and what it allows:

| Property | Written as | Notes |
|---|---|---|
| `title` | `dc:title` | Not empty |
| `language` | `dc:language`, `xml:lang` of the package and of every document | A language tag such as `en` or `fr-CA` |
| `identifier` | `dc:identifier`, the package's unique identifier | Also the key of font obfuscation |
| `modified` | `dcterms:modified`, in UTC to the second; the date of every ZIP entry | Required by EPUB 3 |
| `creators`, `contributors` | `dc:creator`, `dc:contributor`, with `role` (MARC relator) and `file-as` refinements | |
| `publisher`, `description`, `rights`, `source` | `dc:publisher`, `dc:description`, `dc:rights`, `dc:source` | `source` names the printed edition a page list follows |
| `published` | `dc:date` | `YYYY`, `YYYY-MM` or `YYYY-MM-DD` |
| `subjects` | one `dc:subject` each | |
| `otherIdentifiers` | more `dc:identifier` elements | |
| `direction` | `dir` of the package and documents, `page-progression-direction` of the spine | `Direction::Default` writes none |
| `accessibility` | schema.org and EPUB Accessibility metadata | See [Accessibility](accessibility.md) |

## Identifiers

| Factory | Value written | Refinement |
|---|---|---|
| `Identifier::uuid('7c9e…')` | `urn:uuid:7c9e…`, lowercase | none |
| `Identifier::isbn('978-2-…')` | the 13 digits, check digit verified | ONIX code list 5, `15` |
| `Identifier::doi('10.1000/182')` | the DOI | ONIX code list 5, `06` |
| `new Identifier('urn:…', IdentifierType::Urn)` | the value | ONIX code list 5, `22` |
| `new Identifier('X-1')` | the value | ONIX code list 5, `01` (proprietary) |

A book keeps its unique identifier from one edition to the next: reading systems use it to recognise a book they
already hold. Give a new UUID to a new work, not to a corrected file.

## Creators

`new Creator('Ann Writer', 'aut', 'Writer, Ann')`: a name, a MARC relator code of three lowercase letters (`aut`
author, `ill` illustrator, `edt` editor, `trl` translator, `nrt` narrator…) and the form used to sort.

## Chapters

`new Chapter($html, …)` is one content document. Its HTML is a fragment, as found in the body of a page; see
[Content documents](content.md) for what becomes of it.

| Argument | Default | Effect |
|---|---|---|
| `title` | the text of its first heading, else the book's title | The document's `<title>`, its entry in the table of contents when it has no heading, its landmark label |
| `fileName` | `chapter-N.xhtml`, N its position | Its path in the package; ends with `.xhtml`; may hold folders, such as `text/one.xhtml` |
| `landmark` | none | A `LandmarkType`: the `epub:type` of its body and an entry in the landmarks |
| `inToc` | `true` | Whether its headings, or its title, are in the table of contents |
| `linear` | `true` | `false` writes `linear="no"` in the spine, for notes or answers reached by links |
| `language` | the book's | Its own `xml:lang` and `lang` |
| `overlay` | none | A `MediaOverlay`, the narration synchronised with its text: see [Media, overlays and layout](media.md) |
| `layout` | the book's | `Layout::PrePaginated` or `Layout::Reflowable`, when this chapter is laid out unlike the book |
| `viewport` | the book's | The `Viewport` of a pre-paginated chapter |
| `pageSpread` | none | A `PageSpread`: the left page, the right page or the centre of a spread |

## Assets

Every other file is an `Asset`, at a path relative to the package directory made of letters, digits, `.`, `_`, `-`
and `/`:

| Factory | Media type | Notes |
|---|---|---|
| `Asset::stylesheet('css/book.css', $css)` | `text/css` | Linked from every content document, the navigation and the cover, in the order added |
| `Asset::image('images/map.png', $bytes)` | read from the bytes: PNG, JPEG, GIF, WebP or SVG | Or given as third argument |
| `Asset::font('fonts/body.otf', $bytes, obfuscate: true)` | read from the bytes: TrueType, OpenType, WOFF or WOFF2 | Or given as fourth argument |
| `Asset::audio('audio/one.mp3', $bytes)` | read from the bytes: MP3, AAC in MP4, Opus, Vorbis, FLAC or WAV | Or given as third argument; a fallback as fourth |
| `Asset::video('video/clip.mp4', $bytes)` | read from the bytes: MP4, WebM or Ogg | Or given as third argument; a fallback as fourth |
| `Asset::textTrack('video/clip.vtt', $vtt)` | `text/vtt` | A WebVTT file of captions or subtitles |

Stylesheets, images, fonts and text tracks are accepted only in the EPUB core media types, so that they need no
fallback. Audio and video files, and the fallbacks they may carry, are described in
[Media, overlays and layout](media.md). A path is used once in a book; `nav.xhtml`, `toc.ncx`, `cover.xhtml` and
`package.opf` are the library's.

A stylesheet refers to its fonts and images by paths relative to itself (`url(../fonts/body.otf)`). The library does
not read CSS; an application that accepts CSS from its users passes it through a sanitizer first.

## Font obfuscation

A font asset created with `obfuscate: true` is written with the IDPF algorithm of EPUB 3: its first 1040 bytes are
combined with the SHA-1 of the book's unique identifier, and `META-INF/encryption.xml` lists it. Reading systems undo
it; a font copied out of the archive is not directly usable. It is not encryption: it keeps a font licence that asks
for it, such as many commercial licences for embedding in e-books, and nothing more. A font whose licence forbids
embedding cannot be made embeddable by obfuscation.

## Cover

`new Cover($image, $alt)` takes an image asset and its text alternative. The image is marked `cover-image` in the
manifest — reading systems show it in their library — and shown alone in `cover.xhtml`, first in the reading order,
with the `cover` landmark. The image may be among the book's assets or only in the cover.
