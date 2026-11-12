# phil/search-bundle

**Full-text search over your own resources, kept up to date at each Doctrine flush.**

phil/search-bundle gives a Symfony application a search. A `DocumentProvider` the application writes turns one kind of
resource into the documents of an index: the text searched and the fields filtered on. The bundle keeps each index up
to date at each Doctrine flush, searches it, and rebuilds it with `phil:search:reindex`. A search returns the
identifiers found, best first, with their total and the count of each value of the facets asked. Loading the
resources and showing them stays with the application, and so does deciding what may be found: a provider that
returns no document for a draft keeps it out of the index.

A search reads its words with the synonyms of their language: `jdr` also finds `jeu de rôle`. The synonyms come from
providers behind one port: the lists of `phil/search-synonyms`, kept in files or by the administration, the forms a
vocabulary rejects for its preferred terms, or a source of the application's.

The results page gets its filters as a Twig component drawn with the recipes of the PHIL UX kit: a box per value of
each facet with its count, the values chosen as links that remove them one at a time or all together, and the count
of results in a live region. Everything works as plain links and a plain form; a Stimulus controller applies a box as
soon as it is ticked and draws the page again in place, the focus kept and the count said.

It is for a site whose visitors search pages, events or products, and whose developers want one way to describe what
is searchable whatever runs the index. `SearchActionSchema` also gives the schema.org `SearchAction` that lets a
search engine offer a box landing on the site's own results page.

The index sits behind the `SearchIndex` port, and three adapters fill it. The default is a table of the application's
database, through Doctrine DBAL, for a site that needs no engine; it ranks by how often the words come. Meilisearch
works through its HTTP API with Symfony's HTTP client. Loupe keeps SQLite files with no server to run, through the
separate package `phil/search-loupe`. Meilisearch and Loupe bring their own ranking and typo tolerance. Changing
adapter is a line of configuration and a reindex, since the providers do not change.

## Status

| Part | State |
|---|---|
| `Document`, `SearchQuery`, `SearchResult` | Implemented and tested |
| `Search`: index, remove, search, rebuild; `SearchIndex`, `DocumentProvider` | Implemented and tested |
| `DbalSearchIndex`, its table and schema listener | Implemented and tested |
| `MeilisearchIndex` | Implemented and tested |
| Indexing at each flush; `phil:search:reindex` | Implemented and tested |
| Facets: lists of values, counts per value, each facet counted without its own filter | Implemented and tested |
| Synonyms per language: the port, the expander, the store of `phil/search-synonyms`, rejected forms; `phil:search:synonyms` | Implemented and tested |
| `SearchRequest`; the `PhilSearchFilters` component; the `filters` Stimulus controller | Implemented and tested, in a browser too |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, configuration, the adapters. |
| [Providers](doc/providers.md) | Writing a provider; what a flush indexes. |
| [Searching](doc/searching.md) | Queries, filters, facets, pages, ranking. |
| [Synonyms](doc/synonyms.md) | The port, its providers, how a query is expanded, engines that keep synonyms. |
| [Filters](doc/filters.md) | The results page: `SearchRequest`, the component, the controller, without JavaScript. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```php
$result = $search->search('pages', SearchQuery::of('jeux de rôle', ['locale' => 'fr', 'genre' => ['jdr', 'plateau']], facets: ['genre']));
// $result->ids, best first; $result->total; $result->facet('genre'), each value and its count
```

```twig
<twig:PhilSearchFilters :request="request" :result="result" :labels="{genre: 'Genre'}" results="results" />
```

```bash
bin/console phil:search:reindex
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; Doctrine DBAL 4.5 and ORM 3.7; Symfony 8.2's dependency injection and config.
Symfony's HTTP client, for Meilisearch, `phil/search-loupe`, for Loupe, `phil/search-synonyms`, for lists of
synonyms, Symfony UX Twig Component and the recipes of `phil/ux-kit`, for the filters, and Symfony Console are
optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
