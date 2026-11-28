# Changelog

All notable changes to `phil/related-content` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Item`: an identifier of 1 to 255 bytes without control characters, keywords and broader terms by index, compared
  trimmed, in lowercase and once, at most 32 indices and 200 keywords each, a date and a language.
- `KeywordIndex` and `DateProximity`, behind the `Index` port: shared keywords, shared broader terms at a factor, and
  close dates within a window, each weighted and the same both ways.
- `RelevanceRules`: the indices, a threshold under which nothing is proposed, the limit, the candidates read, the
  weights of similarity and of the pair counts, the minimum count of a pair, the items computed again at once, newer
  candidates left out on demand; `forContent()` over `topics`, `authors`, `series` and the date.
- `Neighbourhood`: pinned neighbours first, then typed relations, then the candidates scored above the threshold,
  highest first and by identifier on a tie, each with its reasons strongest first; unpublished items and other
  languages left out, every read bounded.
- `RelatedContent`: the neighbours computed at publication and kept, read by `related()` without computing; the items
  that showed a changed item and its new neighbours computed again, a retired item taken out of every list and its
  pair counts forgotten, the items past the bound returned in `Recomputation::$deferred`.
- `RelatedSet`, `Recommendation` and `Reason`: the kept neighbours by reason, as an array and back.
- Typed relations for products, `TypedRelation` and `RelationType`: accessory, complement shown both ways, upgrade.
- Anonymous pair counts, `PairRecorder`, `Signal`, `PairCount` and `InMemoryPairCounts`: bought together and viewed
  next counted by pair of products, never by person, ignored under a minimum count, forgotten with a retired product.
- `TaxonomyExpander`, and the ports `ItemSource`, `Taxonomy`, `Pins`, `TypedRelations`, `SimilarItems` with
  `Similarity`, `PairCounts` and `RelatedStore`, with `InMemoryRelatedStore`.
- `Bridge\Publishing\PublishingListener`: the neighbours computed from `PagePublished` and `PageRetired` of
  phil/publishing.
- `InvalidItem` and `InvalidRule` under `RelatedContentException`.
- The benchmark `NeighbourhoodBench`, and the documentation in `doc/`: installation, scoring, products, extension
  points, testing and reference.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
