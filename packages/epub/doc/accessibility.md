# Accessibility

An EPUB is accessible when its content can be read by everyone — through a screen reader, a braille display, large
text or reading aloud — and when its metadata says so, so that a reader can know before buying or borrowing. The
library takes care of the structure; the application declares what it knows of its content.

## The metadata

`Metadata::$accessibility` is an `Accessibility` value, written as schema.org properties in the package document:

| Argument | Written as | Example |
|---|---|---|
| `accessModes` | one `schema:accessMode` each | `AccessMode::Textual`, `AccessMode::Visual`, `AccessMode::ChartOnVisual` |
| `sufficientModes` | one `schema:accessModeSufficient` per set, modes joined by commas | `[[AccessMode::Textual]]` when every image has a text alternative |
| `features` | one `schema:accessibilityFeature` each | `AccessibilityFeature::AlternativeText`, `StructuralNavigation`, `ReadingOrder` |
| `hazards` | one `schema:accessibilityHazard` each | `AccessibilityHazard::None`, or `NoFlashingHazard` and its siblings |
| `summary` | `schema:accessibilitySummary` | A sentence a reader can understand |
| `conformsTo` | `dcterms:conformsTo` | `EPUB Accessibility 1.1 - WCAG 2.2 Level AA` |
| `certifiedBy` | `a11y:certifiedBy` | Who checked it |

`AccessibilityHazard::None` and `Unknown`, and `AccessibilityFeature::None`, are declared alone.

## What the writer adds

The writer adds what it knows for itself, after what the application declared and without repeating it:

- the `textual` access mode, always; `visual` when the book holds an image or a video; `auditory` when it holds an
  audio or a video file;
- the `tableOfContents` feature, since every book has its navigation document;
- the `pageNavigation` feature when the book has a page list;
- the `synchronizedAudioText` feature when a chapter has a media overlay;

unless the application declared the feature `none`. It adds no hazard, summary or conformance: only the application
knows them.

## What makes the content accessible

The library writes what it is given. The content is accessible when the HTML is:

- headings in order, which also make the table of contents;
- a text alternative on every image — the library keeps `alt`, and an image it cannot include leaves its alternative
  in the text;
- the language of the book and of each chapter in a different language;
- landmarks on the chapters that have a role (`LandmarkType::TitlePage`, `Index`, `Glossary`…);
- page break markers when the book follows a printed edition, so that a class can say "turn to page 42" — see
  [Navigation](navigation.md).

The navigation document is marked with `role="doc-toc"`, the cover image with `role="doc-cover"`, and the landmarks
and page list are hidden from the reading order.
