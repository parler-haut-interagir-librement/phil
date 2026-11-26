# Changelog

All notable changes to `phil/search-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.0-alpha - 2026-11-25

A behaviour change in alpha, a minor bump — with Meilisearch or Loupe, the entities are
indexed through `phil/outbox-bundle` by default, which an application installs or replaces with `index_entities:
direct`.

### Added

- Indexing through the outbox, `index_entities: outbox`: `OutboxIndexingListener` records, in the transaction of the
  flush, each entity a provider knows that was inserted or updated, by its class and identifier, and each document of an
  entity deleted, by its index and identifier, the records of one entity under one ordering key; `SearchOutboxCodec`
  rebuilds them as `IndexEntity` and `RemoveDocument`, skipping a class no entity manager maps any more, and
  `OutboxIndexer` indexes the entity as it then is, or removes the document, when `phil/outbox-bundle` relays them. A
  flush rolled back records nothing; an engine down leaves the records to the relay, which tries again.

### Added

- `meilisearch.http_client`, `meilisearch.timeout` and `meilisearch.max_duration`: the HTTP client that calls
  Meilisearch, `http_client` by default, and how long a call may take, 5 and 10 seconds by default;
  `MeilisearchIndex::TIMEOUT` and `MAX_DURATION`, and its last two arguments, optional.

### Changed

- The development requirements accept `phil/ux-kit` 0.6, the minor version of its main branch and no earlier one, so
  that the package installs on its own beside that package.
- Requires the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6), and of each development
  requirement (`doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, `symfony/ux-twig-component` 3.8,
  `php-webdriver/webdriver` 1.16, …).
- `index_entities` is `outbox` by default with Meilisearch and Loupe, and needs `phil/outbox-bundle` on the connection
  of the entities; `direct`, the indexing after each flush, stays an option and the default with the table of the
  database; `none` indexes nothing. `true` is the default of the adapter, `false` is `none`. The bundle requires
  `doctrine/persistence`.
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

### Security

- An index outside the database no longer receives the documents of a flush its transaction rolls back, nor misses those
  of a flush committed while the engine was down: with `index_entities: outbox`, the default for Meilisearch and Loupe,
  the index follows the database through the outbox.

### Security

- `MeilisearchIndex` called Meilisearch without a timeout: a slow server held a worker until `default_socket_timeout`,
  60 seconds. Every call waits 5 seconds at most for a byte and lasts 10 at most by default.

## 0.2.0-alpha - 2026-11-18

The index and the identifier of a document compare byte by byte on MySQL and MariaDB.
See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Changed

- `SearchTable::configure()` takes the platform of the connection as its second argument, and the schema listener
  gives it: on MySQL and MariaDB, `index_name` and `document_id` of `phil_search_document` take the `utf8mb4`
  character set and the `utf8mb4_bin` collation. PostgreSQL and SQLite keep their default, which compares bytes
  already.

### Fixed

- Under the default case-insensitive collation of MySQL and MariaDB, indexing the document `post-a` replaced the
  document `Post-A` of the same index, and removing one removed the other.

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
