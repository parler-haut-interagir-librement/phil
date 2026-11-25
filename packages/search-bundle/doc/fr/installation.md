<!-- phil-dev source-sha256=0e6c1283ec19f9d1cdeab282877fc4a3aa73ffd7a3d3629486eeedf9c19e97f3 -->
# Installation

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-mbstring` | toute version | Les mots en minuscules |
| `doctrine/dbal`, `doctrine/orm` | ^4.5, ^3.7 | La table des documents ; l'indexation à chaque `flush` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et sa configuration |
| `symfony/http-client` | facultatif | Meilisearch |
| `phil/search-loupe` | facultatif | Loupe ; un paquet à part, puisque Loupe exige un paquet sous licence GNU GPL |
| `phil/search-synonyms` | facultatif | Des listes de synonymes dans des fichiers ou le magasin de l'application |
| `symfony/ux-twig-component`, `twig/twig`, `symfony/translation` | facultatif | Le composant `PhilSearchFilters` |
| `phil/ux-kit` | facultatif | Les recettes Badge, Button, Checkbox et Field avec lesquelles le composant se dessine, par le Toolkit de Symfony UX |
| `symfony/stimulus-bundle`, `symfony/asset-mapper` | facultatif | Le contrôleur `filters` |
| `symfony/console` | facultatif | `phil:search:reindex`, `phil:search:synonyms` |

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

`adapter: meilisearch` sans URL, `adapter: loupe` sans `phil/search-loupe`, `synonyms.directory` sans
`phil/search-synonyms`, et `index_entities: outbox` — la valeur par défaut avec Meilisearch et Loupe — sans
`phil/outbox-bundle`, sont refusés à la construction du conteneur. Un service `SearchIndex` propre à l'application,
sous forme d'alias, remplace celui qui est configuré.

## Les adaptateurs

| Adaptateur | Emplacement | Pour |
|---|---|---|
| `dbal` | `phil_search_document` : `index_name`, `document_id`, `content` (les mots, en minuscules), `filters` (JSON) ; ajoutée au schéma de l'ORM | Un site de quelques milliers de documents : une recherche avec des mots ou des filtres lit chaque ligne candidate |
| `meilisearch` | Meilisearch, par son API HTTP avec le client HTTP de Symfony, sans SDK : documents, attributs filtrables ajoutés à ceux que l'index possède, recherche, facettes, synonymes | Tout ce qui est plus grand, la tolérance aux fautes de frappe |
| `loupe` | Loupe, des fichiers SQLite sous `loupe.directory`, par `phil/search-loupe` | La tolérance aux fautes de frappe et la pertinence sans serveur à faire tourner |

### Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Un document est remplacé par son index et son
identifiant : les documents `Post-A` et `post-a` sont deux lignes, et indexer l'un ne remplace jamais l'autre. Les mots
gardent la collation de la connexion, sous laquelle la recherche les lit.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_search_document` | `index_name`, `document_id` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ignore la casse et les accents.
Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et SQLite compare
les octets : leurs colonnes gardent la valeur par défaut.

#### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne
brise une clé. Les lignes qu'une collation insensible à la casse avait déjà fusionnées restent fusionnées ; les valeurs
écrites à partir de la migration sont gardées distinctes. Sur PostgreSQL et SQLite, le diff est vide.

#### Mise à niveau vers 0.3

Avec Meilisearch ou Loupe, `index_entities` vaut `outbox` par défaut : installez `phil/outbox-bundle` sur la connexion
des entités, créez sa table et lancez son relais — `phil:outbox:relay` sous un gestionnaire de processus, ou son
horaire. Une application qui garde l'indexation après chaque `flush` met `index_entities: direct` ; `true` reste la
valeur par défaut de l'adaptateur, `false` reste `none`. Voyez [Ce que fait un `flush`](providers.md#ce-que-fait-un-flush).

## Les filtres

Avec Symfony UX Twig Component installé, le bundle enregistre `PhilSearchFilters` ; avec AssetMapper, il associe le
répertoire de son contrôleur à `@phil/search-bundle`. Le contrôleur est déclaré dans `assets/package.json`, que
Symfony UX lit pour l'activer dans `assets/controllers.json`. Voir [Filtres](filters.md).
