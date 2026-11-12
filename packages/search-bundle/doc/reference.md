# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilSearchBundle` | `final class`, `AbstractBundle` | Alias `phil_search`; `PROVIDER_TAG`, `SYNONYM_PROVIDER_TAG`; `LOUPE_INDEX` and `LOUPE_FACTORY`, the classes `adapter: loupe` registers; `SYNONYM_STORE` and `FILE_SYNONYM_STORE`, those of `phil/search-synonyms` |
| `Domain\ValueObject\Document`, `Index`, `SearchQuery`, `SearchResult`, `FacetValue` | values | A document; an index name; a query, its filters, facets, language and terms; a page of results and its facets; a value of a facet and its count |
| `Domain\ValueObject\Phrase`, `SynonymRule` | values | How words are compared; a rule of synonyms, equivalent or one-way |
| `Domain\Exception\SearchException`, `InvalidSearch` | exceptions | Everything the bundle throws |
| `Application\Search` | `final readonly class` | Index, remove, forget, search, rebuild; `addressesOf()`, a resource's place in the indexes |
| `Application\SearchIndex`, `DocumentProvider` | interfaces | The index — index, remove, clear, rebuild, search —; the application's documents |
| `Application\Contract\SynonymProvider`, `RejectedForms`, `SynonymAwareIndex` | interfaces | The synonyms of a language; the forms a vocabulary rejects; an index that keeps synonyms itself |
| `Application\SynonymExpander` | `final class`, `kernel.reset` | The synonyms of every provider applied to a query; the list an engine is given |
| `Application\RejectedFormSynonyms` | `final readonly class` | Rejected forms as one-way synonyms |
| `Application\SearchRequest` | `final readonly class` | What a visitor asked a results page; the query strings that remove filters |
| `Application\SearchActionSchema` | `final class` | The site's search as a schema.org `WebSite` with its `SearchAction` |
| `Infrastructure\Doctrine\DbalSearchIndex`, `SearchTable`, `SearchSchemaListener` | adapter | A table of the database |
| `Infrastructure\Doctrine\IndexOnFlushListener` | Doctrine listener, `kernel.reset` | Entities indexed at each flush |
| `Infrastructure\Meilisearch\MeilisearchIndex`, `SearchFailed` | adapter | Meilisearch's HTTP API; `REBUILD_SUFFIX` |
| `Infrastructure\Console\ReindexCommand`, `SynonymsCommand` | console commands | `phil:search:reindex`, `phil:search:synonyms` |
| `Infrastructure\SearchSynonyms\SynonymStoreProvider` | adapter | The store of `phil/search-synonyms` as a `SynonymProvider` |
| `Infrastructure\Twig\Components\SearchFiltersComponent` | Twig component | `PhilSearchFilters` |
| `Infrastructure\Twig\KitComponents`, `Infrastructure\DependencyInjection\KitSubdirectoryPass` | services | The names of the recipes of the kit |
| `assets/src/filters_controller.js` | Stimulus controller | `phil--search-bundle--filters` |
