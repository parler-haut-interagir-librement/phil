# Changelog

All notable changes to `phil/epub-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Epubs`: a book written to bytes or kept in the storage, and the book a source builds from an identifier.
- `BookSource`, `#[AsBookSource]` and `BookSources`: the application's books built from an identifier, registered by
  name, and `UnknownBookSource`.
- The `EpubStorage` port, `DirectoryStorage`, which refuses a path that leaves its directory, `MediaLibraryStorage`,
  which keeps each book as a medium of `phil/media-bundle`, in a collection when configured, and `StorageFailed`.
- `PhilEpubBundle` and the `phil_epub` configuration: `temporary_directory`, `storage` (`directory` or `media`, the
  directory, the media collection) and `fonts` (obfuscation, folder, stylesheet).
- The Messenger message `WriteEpub`, carrying a source name, an identifier and a path, `EpubHandler`, which retries a
  failure that may pass, and the `EpubStored` event.
- The command `phil:epub:write`, to the storage or to a file (`--output`).
- `EpubFonts`, the families of the font directory of `phil/fonts-bundle` embedded in a book — the smallest file of each
  face, obfuscated when configured, a stylesheet of their rules —, refusing a face whose licence forbids embedding or
  allows bitmaps only with `FontNotEmbeddable`; `MediaAssets`, the media of `phil/media-bundle` as images, a cover with
  its alternative text, or fonts; `RequiredServicesPass`, which removes them when their bundle is not enabled.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
