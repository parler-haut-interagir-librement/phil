# Searching

```php
$result = $search->search('games', SearchQuery::of(
    'jeu de rôle',
    ['locale' => 'fr', 'genre' => ['jdr', 'plateau']],
    limit: 20,
    offset: 40,
    facets: ['genre', 'year'],
));
```

| Part | What it is |
|---|---|
| The text | Words a document must all hold, without regard to case; each word, or phrase, may have alternatives from the [synonyms](synonyms.md) |
| The filters | Field and value pairs a document must all have; a list of values is any of them; a list field of a document holds the value among its values; a value is compared as it is, accents, slashes and quotes included; a list holds 1 to 100 values |
| `limit`, `offset` | A page of 1 to 1000 documents, from 0 |
| `facets` | The filter fields whose values are counted, 20 at most |
| `language` | The language of the words, `fr` or `fr-CA`, for their synonyms; by default that of the filter `locale` when it is one value |

`SearchResult::$ids` holds the identifiers found, best first, and `$total` how many there are in all. An index name
that is not one — 1 to 64 lower-case letters, digits or `_`, starting with a letter — is refused with
`InvalidSearch` before any adapter sees it, so a name taken from a request never reaches a path or a URL.

## Facets

`SearchResult::facet('genre')` gives the values of a facet as `FacetValue` — `$value`, always text, and `$count` —,
most found first, then by value, 100 at most; a facet with no value found gives none. Each facet is counted among the
documents the query finds once the filter of that same facet is left out: a visitor who chose `jdr` still sees how
many `plateau` would find, and choosing it widens the results instead of emptying them.

| Adapter | How facets are counted |
|---|---|
| `dbal` | On the rows the search reads, in PHP |
| `meilisearch` | Meilisearch's facet distribution; a facet whose filter is set is counted by one more query without that filter, the queries sent together to `/multi-search` |
| `loupe` | Loupe's facet distribution, one more search without hits per facet whose filter is set; a facet Loupe has never seen as a filter gives no value |

## Ranking

With `dbal`, a document is ranked by how often the terms, and their alternatives, come in it, then by identifier; a
search with words, filters or facets reads every candidate row, a search with none of them reads one page and counts
the rest in SQL. With Meilisearch and Loupe, by the engine's own ranking, its typo tolerance included; Loupe, like
`dbal`, finds a document only when it holds every word.

## schema.org

```php
$data = SearchActionSchema::of('https://example.com/', 'https://example.com/search?q={search_term_string}', 'Le site');
```

A `WebSite` whose `potentialAction` is a `SearchAction` on the site's own results page, so that an engine can offer
a search box that lands there. The results URL holds `{search_term_string}` where the words go; one without it is
refused. Print it on the home page as JSON-LD, with `JSON_HEX_TAG`.
