# Testing

| Area | What is covered |
|---|---|
| `Document`, `Index`, `SearchQuery`, `SearchResult`, `Phrase`, `SynonymRule` | Every check and bound; words split on any space; lists of values, facets, languages, terms; facets sorted and capped |
| `SynonymExpander`, `RejectedFormSynonyms`, `SynonymStoreProvider` | The longest phrase first, rules merged, alternatives capped, a language read once until reset, the list an engine is given, rejected forms |
| `SearchRequest`, `PhilSearchFilters`, `KitComponents` | What a query string holds and what is left out, the links removing filters; the facets and values shown, the names of the recipes |
| `DbalSearchIndex`, its table and listener | Words and filters, escaping, ranking, pages, replacing, removing, clearing, a rebuild in one transaction, index names, alternatives and phrases, lists of values, facets counted without their own filter |
| `MeilisearchIndex` | The requests sent, filterable attributes merged with the index's, filter values with accents, slashes and quotes, lists with `IN`, facets in one search or in `/multi-search`, synonyms, a rebuild by swap, the answers read, refusals, index names |
| `Search`, `IndexOnFlushListener`, `ReindexCommand`, `SynonymsCommand` | Indexing, several resources in one call and their removals one call per index, drafts, several providers, rebuilds in batches and their failure, index names, flushes, their entities indexed together in the order of the changes, deletions under the identifier read before the flush, failed and cleared flushes, the commands, the synonyms read or given to the engine |
| `PhilSearchBundle` | The three adapters, the synonyms, the component, the controller's directory, settings refused |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, pages and notes and their providers:
pages are found as they are flushed, renamed and deleted, and rebuilt; a note, whose identifier the database
generates, leaves the index when it is deleted. The same kernel draws a results page of games with the recipes of the
PHIL UX kit copied where the Toolkit would put them: its filters are ticked and removed by plain forms and links, and
its words read the synonyms of a directory.

```sh
vendor/bin/phpunit packages/search-bundle/tests
cd packages/search-bundle && node --import ./tests/js/register.mjs --test tests/js
```

`tests/js` runs the controller under Node's test runner, its peer dependency stood for by a stub.

## In a browser

`tests/Browser` drives the controller in a headless Chrome with Panther, on the results page of the functional kernel
served by PHP's own server: a box ticked draws the page again in place, says the count and keeps the focus; a link
removing a filter is followed in place; going back draws the previous filters; the page is never reloaded. From the
monorepo root, with the chromedriver of the system:

```sh
composer test:browser
```

## Benchmarks

The phpbench subjects in `tests/Benchmark` measure the hot paths at several sizes: the database index, with facets,
and the expander of synonyms, at the first search of a request and the next ones. From the monorepo root:

```sh
composer bench -- packages/search-bundle/tests/Benchmark
```
