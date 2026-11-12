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
    loupe:
        directory: '%kernel.project_dir%/var/loupe'   # a directory per index
    index_entities: true     # entities indexed at each flush
    batch: 500               # documents a rebuild sends at once
    synonyms:
        directory: ~         # files of synonyms, fr.txt, en.txt, with phil/search-synonyms
        store: ~             # or the service of a store of phil/search-synonyms
        rejected_forms: []   # the services giving the forms a vocabulary rejects
        languages: []        # the languages whose synonyms Meilisearch is given
    kit_subdirectory: ~      # where the recipes of the kit are; the Toolkit's subdirectory when null
```

`adapter: meilisearch` without a URL, `adapter: loupe` without `phil/search-loupe`, and `synonyms.directory` without
`phil/search-synonyms`, are refused when the container is built. A `SearchIndex` service of the
application's own, aliased, replaces the configured one.

## The adapters

| Adapter | Where | For |
|---|---|---|
| `dbal` | `phil_search_document`: `index_name`, `document_id`, `content` (the words, lower-case), `filters` (JSON); added to the ORM's schema | A site of a few thousand documents: a search with words or filters reads every candidate row |
| `meilisearch` | Meilisearch, by its HTTP API with Symfony's HTTP client, no SDK: documents, filterable attributes added to those the index has, search, facets, synonyms | Anything larger, typo tolerance |
| `loupe` | Loupe, SQLite files under `loupe.directory`, through `phil/search-loupe` | Typo tolerance and relevance without a server to run |

## The filters

With Symfony UX Twig Component installed, the bundle registers `PhilSearchFilters`; with AssetMapper, it maps the
directory of its controller as `@phil/search-bundle`. The controller is declared in `assets/package.json`, which
Symfony UX reads to enable it in `assets/controllers.json`. See [Filters](filters.md).
