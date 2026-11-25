# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-mbstring` | any | Words in lower case |
| `doctrine/dbal`, `doctrine/orm` | ^4.5, ^3.7 | The table of documents; indexing at each flush |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration |
| `symfony/http-client` | optional | Meilisearch |
| `phil/search-loupe` | optional | Loupe; a package of its own, since Loupe requires a package under the GNU GPL |
| `phil/search-synonyms` | optional | Lists of synonyms in files or the application's store |
| `symfony/ux-twig-component`, `twig/twig`, `symfony/translation` | optional | The `PhilSearchFilters` component |
| `phil/ux-kit` | optional | The recipes Badge, Button, Checkbox and Field the component draws with, through the Symfony UX Toolkit |
| `symfony/stimulus-bundle`, `symfony/asset-mapper` | optional | The `filters` controller |
| `symfony/console` | optional | `phil:search:reindex`, `phil:search:synonyms` |

```sh
composer require phil/search-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\SearchBundle\PhilSearchBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_search.yaml
phil_search:
    adapter: dbal            # dbal, meilisearch or loupe
    connection: default      # the table's connection, and whose entities are indexed
    meilisearch:
        url: '%env(MEILISEARCH_URL)%'
        key: '%env(MEILISEARCH_KEY)%'
        http_client: http_client  # the HTTP client that calls Meilisearch
        timeout: 5.0              # the longest wait for a byte of an answer, in seconds
        max_duration: 10.0        # the longest call, in seconds
    loupe:
        directory: '%kernel.project_dir%/var/loupe'   # a directory per index
    index_entities: ~        # outbox (Meilisearch, Loupe), direct (dbal) or none; null: the adapter's default
    batch: 500               # documents a rebuild sends at once
    synonyms:
        directory: ~         # files of synonyms, fr.txt, en.txt, with phil/search-synonyms
        store: ~             # or the service of a store of phil/search-synonyms
        rejected_forms: []   # the services giving the forms a vocabulary rejects
        languages: []        # the languages whose synonyms Meilisearch is given
    kit_subdirectory: ~      # where the recipes of the kit are; the Toolkit's subdirectory when null
```

`adapter: meilisearch` without a URL, `adapter: loupe` without `phil/search-loupe`, `synonyms.directory` without
`phil/search-synonyms`, and `index_entities: outbox` — the default with Meilisearch and Loupe — without
`phil/outbox-bundle`, are refused when the container is built. A `SearchIndex` service of the
application's own, aliased, replaces the configured one.

## The adapters

| Adapter | Where | For |
|---|---|---|
| `dbal` | `phil_search_document`: `index_name`, `document_id`, `content` (the words, lower-case), `filters` (JSON); added to the ORM's schema | A site of a few thousand documents: a search with words or filters reads every candidate row |
| `meilisearch` | Meilisearch, by its HTTP API with Symfony's HTTP client, no SDK: documents, filterable attributes added to those the index has, search, facets, synonyms | Anything larger, typo tolerance |
| `loupe` | Loupe, SQLite files under `loupe.directory`, through `phil/search-loupe` | Typo tolerance and relevance without a server to run |

### Collation of the keys

**Keys compare byte by byte**, as PHP compares them. A document is replaced by its index and identifier: the documents
`Post-A` and `post-a` are two rows, and indexing one never replaces the other. The words keep the connection's
collation, which the search reads them under.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_search_document` | `index_name`, `document_id` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

#### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

#### Upgrading to 0.3

With Meilisearch or Loupe, `index_entities` is `outbox` by default: install `phil/outbox-bundle` on the connection of
the entities, create its table, and run its relay — `phil:outbox:relay` under a process manager, or its schedule. An
application that keeps indexing after each flush sets `index_entities: direct`; `true` stays the default of the
adapter, `false` stays none. See [What a flush does](providers.md#what-a-flush-does).

## The filters

With Symfony UX Twig Component installed, the bundle registers `PhilSearchFilters`; with AssetMapper, it maps the
directory of its controller as `@phil/search-bundle`. The controller is declared in `assets/package.json`, which
Symfony UX reads to enable it in `assets/controllers.json`. See [Filters](filters.md).
