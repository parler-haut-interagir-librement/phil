<!-- phil-dev source-sha256=5bc52d2d6b868bdbb219ee8f1ddea493ef5e73155030a79a6ce2c49f669bbad2 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilSearchBundle` | `final class`, `AbstractBundle` | Alias `phil_search` ; `PROVIDER_TAG`, `SYNONYM_PROVIDER_TAG` ; `LOUPE_INDEX` et `LOUPE_FACTORY`, les classes qu'enregistre `adapter: loupe` ; `SYNONYM_STORE` et `FILE_SYNONYM_STORE`, celles de `phil/search-synonyms` |
| `Domain\ValueObject\Document`, `Index`, `SearchQuery`, `SearchResult`, `FacetValue` | valeurs | Un document ; un nom d'index ; une requête, ses filtres, ses facettes, sa langue et ses termes ; une page de résultats et ses facettes ; une valeur de facette et son compte |
| `Domain\ValueObject\Phrase`, `SynonymRule` | valeurs | La façon de comparer les mots ; une règle de synonymes, d'équivalence ou à sens unique |
| `Domain\Exception\SearchException`, `InvalidSearch` | exceptions | Tout ce que lève le bundle |
| `Application\Search` | `final readonly class` | Indexer, retirer, oublier, chercher, reconstruire ; `addressesOf()`, la place d'une ressource dans les index |
| `Application\SearchIndex`, `DocumentProvider` | interfaces | L'index — indexer, retirer, vider, reconstruire, chercher — ; les documents de l'application |
| `Application\Contract\SynonymProvider`, `RejectedForms`, `SynonymAwareIndex` | interfaces | Les synonymes d'une langue ; les formes qu'un vocabulaire rejette ; un index qui garde lui-même des synonymes |
| `Application\SynonymExpander` | `final class`, `kernel.reset` | Les synonymes de chaque fournisseur appliqués à une requête ; la liste que reçoit un moteur |
| `Application\RejectedFormSynonyms` | `final readonly class` | Les formes rejetées sous forme de synonymes à sens unique |
| `Application\SearchRequest` | `final readonly class` | Ce qu'un visiteur a demandé à une page de résultats ; les chaînes de requête qui retirent des filtres |
| `Application\SearchActionSchema` | `final class` | La recherche du site sous forme de `WebSite` schema.org avec sa `SearchAction` |
| `Infrastructure\Doctrine\DbalSearchIndex`, `SearchTable`, `SearchSchemaListener` | adaptateur | Une table de la base de données |
| `Infrastructure\Doctrine\IndexOnFlushListener` | écouteur Doctrine, `kernel.reset` | Les entités indexées à chaque `flush` |
| `Infrastructure\Meilisearch\MeilisearchIndex`, `SearchFailed` | adaptateur | L'API HTTP de Meilisearch ; `REBUILD_SUFFIX` |
| `Infrastructure\Console\ReindexCommand`, `SynonymsCommand` | commandes de console | `phil:search:reindex`, `phil:search:synonyms` |
| `Infrastructure\SearchSynonyms\SynonymStoreProvider` | adaptateur | Le magasin de `phil/search-synonyms` sous forme de `SynonymProvider` |
| `Infrastructure\Twig\Components\SearchFiltersComponent` | composant Twig | `PhilSearchFilters` |
| `Infrastructure\Twig\KitComponents`, `Infrastructure\DependencyInjection\KitSubdirectoryPass` | services | Les noms des recettes du kit |
| `assets/src/filters_controller.js` | contrôleur Stimulus | `phil--search-bundle--filters` |
