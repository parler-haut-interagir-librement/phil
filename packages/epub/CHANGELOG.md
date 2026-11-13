# Changelog

All notable changes to `phil/epub` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Metadata`, `Identifier`, `IdentifierType`, `Creator` and `Direction`: the title, language, unique identifier and
  modification time EPUB requires, then creators and contributors with MARC roles and sort forms, publisher,
  description, publication date, subjects, rights, source, other identifiers — UUID, ISBN-13 with its check digit,
  DOI, URN, proprietary, refined by ONIX code list 5 — and the direction of text and pages.
- `Accessibility`, `AccessMode`, `AccessibilityFeature` and `AccessibilityHazard`: the schema.org access modes,
  sufficient modes, features, hazards and summary, the conformance and certifier of EPUB Accessibility 1.1, with
  contradictory declarations refused.
- `Chapter`, `LandmarkType`, `Asset`, `AssetKind` and `Cover`: content documents from fragments of HTML with their
  title, file name, landmark, place in the table of contents and in the linear reading order, language, media overlay,
  layout, viewport and page spread; stylesheets, images and fonts in EPUB core media types read from their bytes;
  audio and video files read from their bytes, with manifest fallbacks whose chain ends in a core type for audio, and
  WebVTT text tracks; the cover image and its text alternative.
- `MediaOverlay`, `Clip` and `Narration`: media overlays written as SMIL documents beside their chapters, their
  elements and audio clips checked against the book, their durations, narrators and active classes declared in the
  package document.
- `Rendition`, `Layout`, `Viewport`, `Orientation`, `Spread` and `PageSpread`: fixed-layout books and chapters, the
  viewport of each page, the orientation, the spread and the side of the spread each chapter takes.
- `Book`, `TocEntry` and `NavigationLabels`: a book as a value, its table of contents built from the headings down to a
  depth or given, the words of its navigation document, its navigation document in the spine or not, the map of the
  application's addresses, its rendition, its narration, and the EPUB 2 table of contents `toc.ncx` when asked.
- The conversion of purified HTML to XHTML content documents: parsed as HTML5, written as XML with SVG and MathML in
  their namespace, scripts, handlers and what XML refuses left out, identifiers deduplicated, headings and page break
  markers found and given identifiers.
- Links rewritten to the chapter holding their target, resources checked against the book's assets, remote audio and
  video declared, every other address dropped.
- `HtmlSplitter`: a fragment of HTML cut into chapters at its top-level headings.
- The package document with its metadata, accessibility metadata completed by what the writer knows — the visual and
  auditory access modes, the page navigation and synchronised audio features —, rendition and media overlay metadata,
  manifest with its properties, fallbacks and media overlays, and spine with its properties; the navigation document
  with its table of contents, landmarks and page list; the cover document; the container; font obfuscation with the IDPF
  algorithm and its `encryption.xml`.
- `EpubWriter`: the ZIP archive with the `mimetype` entry first and stored, every entry dated by the modification time,
  written to a file or to a string through a temporary file.
- `Ujo\UjoChapters`: a UJO document rendered by `phil/ujo` and split into chapters, its page breaks numbered as page
  break markers that open the next chapter.
- `EpubException`, implemented by `InvalidBook` and `WriteFailed`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
