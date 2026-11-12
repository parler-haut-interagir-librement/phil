# Changelog

All notable changes to `phil/search-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `Document`, `Index`, `SearchQuery`, `SearchResult`, `SearchException` and `InvalidSearch`; a filter that is a list
  of values, any of which a document may have; facets, counted per value in `SearchResult::$facets` as `FacetValue`,
  most first, each facet counted without its own filter; the language of a query, given or read from its filter
  `locale`; its terms and their alternatives; `Phrase`, how words are compared.
- Synonyms per language: `SynonymRule`, equivalent or one-way; the port `SynonymProvider`, tagged
  `phil_search.synonym_provider` by autoconfiguration; `SynonymExpander`, which reads the rules of a language once
  until the container is reset and gives each phrase of a query its alternatives, longest phrase first;
  `RejectedForms` and `RejectedFormSynonyms`, the forms a vocabulary rejects as one-way synonyms;
  `SynonymAwareIndex`, for an engine that keeps synonyms itself; `SynonymStoreProvider`, the store of
  `phil/search-synonyms`; `Search::publishSynonyms()`, also called after a rebuild; `SynonymsCommand`,
  `phil:search:synonyms`.
- The filters of a results page: `SearchRequest`, the words, the values chosen and the page read from a query string,
  and the query strings that remove a filter or all of them; the Twig component `PhilSearchFilters`, drawn with the
  recipes Badge, Button, Checkbox and Field of the PHIL UX kit, working as plain links and a plain form, its count of
  results in a live region; `KitComponents` and `KitSubdirectoryPass`; the translations `phil_search` in English and
  French; the Stimulus controller `filters`, which applies a box as it is ticked, follows the links in place, draws
  the filters and the results again without a reload, gives the focus back, says the count, and draws the previous
  filters on going back; its directory mapped for AssetMapper.
- `Search`, `SearchIndex` and `DocumentProvider`; `Search::index()`, which takes several resources and sends their
  documents in one call to the index; `Search::addressesOf()`, `Search::removeAt()` and `Search::forget()`; every
  index name checked before an adapter sees it; `SearchIndex::rebuild()`, which keeps the index searchable and leaves
  it as it was when the rebuild fails.
- `SearchActionSchema`: the site's search as a schema.org `WebSite` with its `SearchAction`.
- `DbalSearchIndex`, whose rebuild is one transaction, whose removal of several documents is one statement, whose
  search without words, filters or facets pages in SQL, which finds a term by any of its alternatives, a phrase across
  a line break, and counts facets on the rows it reads; `SearchTable`; `SearchSchemaListener`.
- `MeilisearchIndex`, which adds filterable attributes to those the index has and writes them only when they grow,
  quotes filter values as they are, filters on a list with `IN`, counts facets with its facet distribution — one more
  query per facet whose filter is set, sent together to `/multi-search` —, writes the synonyms of the index, and
  rebuilds into `{index}__rebuild` before swapping; `SearchFailed`.
- The adapter `loupe`, with the key `loupe.directory`: the `LoupeIndex` of `phil/search-loupe`, named and registered
  when that package is installed; `LOUPE_INDEX` and `LOUPE_FACTORY`.
- `PhilSearchBundle`, extension alias `phil_search`, keys `adapter`, `connection`, `meilisearch`, `loupe`,
  `index_entities`, `batch`, `synonyms` — `languages`, `directory`, `store`, `rejected_forms` — and
  `kit_subdirectory`.
- `IndexOnFlushListener`, which reads a deleted entity's place in the indexes before the flush, notes what a flush
  will do per entity manager, indexes the entities of a flush together and its removals one call per index, in the
  order of the changes, and forgets it when the flush fails, the entity manager is cleared or the container is reset.
- `ReindexCommand`.
- The benchmarks of the database index, with facets, and of the expander of synonyms.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
