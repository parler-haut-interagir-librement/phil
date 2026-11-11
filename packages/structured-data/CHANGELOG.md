# Changelog

All notable changes to `phil/structured-data` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Node`: an immutable schema.org node with its types, its `@id` and its properties — texts, numbers, booleans,
  dates written as ISO 8601, backed enums written as their values, language strings, references, nested nodes and
  lists of them —, its terms and identifiers checked, 32 levels deep at most; `Node::fromArray()` reads schema.org data
  as JSON-LD decodes it; `mergedWith()` merges two nodes of one thing.
- `Reference`, `LanguageString`, `LanguageTag` (BCP 47 tags, locales with underscores read too) and `Id`, whose
  `Id::of()` makes stable identifiers of an address and a fragment; `InvalidStructuredData`.
- `Graph`: the nodes of a page, merged by `@id` where the first stands, nested nodes with an `@id` lifted to the top
  and replaced by references, the language of its texts written in its context, 10,000 nodes at most.
- Factories of `Thing`, `WebSite` (with its `SearchAction`), `WebPage`, `BreadcrumbList`, `Organization`,
  `LocalBusiness`, `Person`, `Event`, `Place`, `PostalAddress`, `Product`, `Offer`, `Book`, `CreativeWork`, `Article`,
  `SoftwareSourceCode`, `SoftwareApplication`, `FAQPage` and `ImageObject`, with the enums `EventStatus`,
  `EventAttendanceMode`, `ItemAvailability` and `BookFormat`.
- `JsonLd`: the JSON-LD of a graph, and its one script element with the nonce of a content security policy, unable to
  be closed by its data.
- `Validator`, `Rules`, `TypeRules`, `Report`, `Issue` and `Severity`: the required and recommended properties of
  each type and of its parents, dates, addresses, currencies and languages checked, references to missing nodes and
  untyped nodes reported.
- `Schema`: the factories of `Article`, `Product`, `Offer`, `Event`, `Place`, `PostalAddress`, `Organization`,
  `Person`, `WebSite`, `WebPage`, `BreadcrumbList` and `ImageObject`, each node checked by the validator at once and
  refused with its errors.
- `JsonLdReader` and `Extraction`: the JSON-LD elements of a page read back into one graph, bounded for pages written
  by anyone, with a fuzz target.
- `NodeCollector`, the port where the parts of an application pour the nodes of a page.
- A benchmark of the assembly and the writing of a page's graph.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
