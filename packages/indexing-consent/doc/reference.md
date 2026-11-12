# Reference

## `Indexing`

`Phil\IndexingConsent\Indexing` — a backed enum, stored as its value.

| Case | Value | Meaning |
|---|---|---|
| `Listed` | `listed` | Everywhere: search engines, the sitemap, the structured data, the site's own listings. |
| `NotIndexed` | `not_indexed` | On the site and in its listings, out of search engines, the sitemap and the structured data. |
| `Unlisted` | `unlisted` | Reachable only by its address: out of the site's own listings and search too. |

What each reader is answered by `allows(Reader $reader): bool`:

| | `SearchEngines` | `Sitemap` | `StructuredData` | `SiteListings` |
|---|---|---|---|---|
| `Listed` | yes | yes | yes | yes |
| `NotIndexed` | no | no | no | yes |
| `Unlisted` | no | no | no | no |

| Method | Returns |
|---|---|
| `robotsDirectives(): list<string>` | `['noindex', 'noarchive']` for a record that is not listed, `[]` for one that is. |
| `robots(): ?string` | The same, joined as `<meta name="robots" content="…">` and `X-Robots-Tag` take them: `noindex, noarchive`, or `null`. |
| `isStricterThan(Indexing $other): bool` | Whether this value keeps the record narrower than the other: `Unlisted`, then `NotIndexed`, then `Listed`. |
| `strictest(Indexing $first, Indexing ...$others): Indexing` | The strictest of the values, for a record whose owner and whose page each decide. |
| `allowing(Reader $reader): list<Indexing>` | The values the reader allows, from the widest: what a query keeps when it filters on the stored value rather than asking each record. `[Listed]` for the search engines, the sitemap and the structured data; `[Listed, NotIndexed]` for the site's listings. |

`noindex` keeps the page out of the results; `noarchive` asks the engines not to keep a copy of it, so that a withdrawn
record does not survive in a cache; Google no longer keeps one and ignores it, other engines read it. The page itself
stays reachable: a robot that never fetches it cannot read its directives, so do not also forbid it in `robots.txt`.
The directives keep the page out of the next crawl; a copy an engine already holds leaves it on that crawl, or sooner
through the engine's own removal tool.

## `Reader`

`Phil\IndexingConsent\Reader` — what reads a record beyond its own page.

| Case | Value | Who asks |
|---|---|---|
| `SearchEngines` | `search_engines` | The page, for its robots directives. |
| `Sitemap` | `sitemap` | The sitemap, before listing the page. |
| `StructuredData` | `structured_data` | The structured data of the page, before describing its subject. |
| `SiteListings` | `site_listings` | The site's directory, search and pages of records. |

## `HasIndexingConsent`

`Phil\IndexingConsent\Contract\HasIndexingConsent::indexing(): Indexing` — the record's answer. See
[Extension points](extension-points.md).

## `IndexingPolicy`

`Phil\IndexingConsent\IndexingPolicy` — static, for any object.

| Method | Returns |
|---|---|
| `of(object $record): Indexing` | The record's answer, or `Listed` for an object without the contract: nobody withdrew it. |
| `ofAll(object ...$records): Indexing` | The strictest answer of the records a page shows; `Listed` for none. |
| `allows(object $record, Reader $reader): bool` | Whether the reader may use the record. |
| `filter(iterable $records, Reader $reader): iterable` | The records the reader may use, lazily, in their order, their keys kept. |

| Constant | Value | Meaning |
|---|---|---|
| `REQUEST_ATTRIBUTE` | `_indexing` | The attribute of the request of a page under which the page keeps the `Indexing` of the record it shows, so that every reader of the page — its structured data, its robots directives — reads one answer. |
