# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\Epub\Book` | `final readonly class` | A book: metadata, chapters, assets, cover, table of contents, labels, address map, rendition, narration, EPUB 2 table of contents |
| `Phil\Epub\Metadata` | `final readonly class` | Title, language, identifiers, modification time, creators and the other metadata |
| `Phil\Epub\Identifier` | `final readonly class` | A UUID, ISBN-13, DOI, URN or proprietary identifier |
| `Phil\Epub\IdentifierType` | enum | The scheme of an identifier and its ONIX code |
| `Phil\Epub\Creator` | `final readonly class` | A creator or contributor, its MARC role and sort form |
| `Phil\Epub\Direction` | enum | Default, left to right, right to left |
| `Phil\Epub\Chapter` | `final readonly class` | A content document from a fragment of HTML, with its media overlay and its layout |
| `Phil\Epub\LandmarkType` | enum | The structural role of a chapter |
| `Phil\Epub\Asset` | `final readonly class` | A stylesheet, image, font, audio or video file or text track, its media type, obfuscated or not, its fallback |
| `Phil\Epub\AssetKind` | enum | Stylesheet, image, font, audio, video, text track |
| `Phil\Epub\MediaOverlay` | `final readonly class` | The narration of a chapter, its clips in reading order |
| `Phil\Epub\Clip` | `final readonly class` | An element of a chapter and the part of an audio file that reads it |
| `Phil\Epub\Narration` | `final readonly class` | The narrators and the classes of the media overlays |
| `Phil\Epub\Rendition` | `final readonly class` | The layout of the book, its viewport, orientation and spread |
| `Phil\Epub\Layout` | enum | Reflowable, pre-paginated |
| `Phil\Epub\Viewport` | `final readonly class` | The size of a fixed-layout page |
| `Phil\Epub\Orientation` | enum | Auto, landscape, portrait |
| `Phil\Epub\Spread` | enum | When two pages are shown side by side: auto, none, landscape, both |
| `Phil\Epub\PageSpread` | enum | The side of a spread a chapter takes: left, right, centre |
| `Phil\Epub\Cover` | `final readonly class` | The cover image and its text alternative |
| `Phil\Epub\TocEntry` | `final readonly class` | An entry of a given table of contents |
| `Phil\Epub\NavigationLabels` | `final readonly class` | The words of the navigation document |
| `Phil\Epub\HtmlSplitter` | `final readonly class` | Cuts a fragment of HTML into chapters at its headings |
| `Phil\Epub\EpubWriter` | `final readonly class` | Writes a book to a file or to a string |
| `Phil\Epub\Accessibility\Accessibility` | `final readonly class` | The accessibility declarations of the publisher |
| `Phil\Epub\Accessibility\AccessMode` | enum | The schema.org access modes |
| `Phil\Epub\Accessibility\AccessibilityFeature` | enum | The schema.org accessibility features |
| `Phil\Epub\Accessibility\AccessibilityHazard` | enum | The schema.org accessibility hazards |
| `Phil\Epub\Ujo\UjoChapters` | `final readonly class` | The chapters of a UJO document, rendered by `phil/ujo` |
| `Phil\Epub\Exception\EpubException` | interface | Implemented by every exception of the library |
| `Phil\Epub\Exception\InvalidBook` | `final class` | A book that cannot be written as described |
| `Phil\Epub\Exception\WriteFailed` | `final class` | An archive that cannot be written |

The classes under `Phil\Epub\Internal`, and `Phil\Epub\Ujo\PageMarker`, are marked `@internal`: the assembler, the
XHTML conversion, the package and navigation documents, the EPUB 2 table of contents, the SMIL documents, paths and
font obfuscation. They may change without notice.
