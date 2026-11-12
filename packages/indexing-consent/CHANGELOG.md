# Changelog

All notable changes to `phil/indexing-consent` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `Indexing`: `Listed`, `NotIndexed` and `Unlisted`; `allows()`, `robotsDirectives()`, `robots()`,
  `isStricterThan()`, `strictest()` and `allowing()`, the values a reader allows for a query on the stored value.
- `Reader`: the search engines, the sitemap, the structured data and the site's own listings.
- `HasIndexingConsent`, the contract of a public record whose reach the person it is about decides.
- `IndexingPolicy`: `of()`, `ofAll()`, `allows()` and `filter()`, a record without the contract being listed, and
  `REQUEST_ATTRIBUTE`, under which the request of a page carries the choice of the record it shows.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
