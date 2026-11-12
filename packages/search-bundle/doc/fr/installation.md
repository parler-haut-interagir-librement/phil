<!-- phil-dev source-sha256=10e9281abddff8b9e8333da1963b60246a8b738c7005bed02a402aa5bcaf18fe -->
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

`adapter: meilisearch` sans URL, `adapter: loupe` sans `phil/search-loupe` et `synonyms.directory` sans
`phil/search-synonyms` sont refusés à la construction du conteneur. Un service `SearchIndex` propre à l'application,
sous forme d'alias, remplace celui qui est configuré.

## Les adaptateurs

| Adaptateur | Emplacement | Pour |
|---|---|---|
| `dbal` | `phil_search_document` : `index_name`, `document_id`, `content` (les mots, en minuscules), `filters` (JSON) ; ajoutée au schéma de l'ORM | Un site de quelques milliers de documents : une recherche avec des mots ou des filtres lit chaque ligne candidate |
| `meilisearch` | Meilisearch, par son API HTTP avec le client HTTP de Symfony, sans SDK : documents, attributs filtrables ajoutés à ceux que l'index possède, recherche, facettes, synonymes | Tout ce qui est plus grand, la tolérance aux fautes de frappe |
| `loupe` | Loupe, des fichiers SQLite sous `loupe.directory`, par `phil/search-loupe` | La tolérance aux fautes de frappe et la pertinence sans serveur à faire tourner |

## Les filtres

Avec Symfony UX Twig Component installé, le bundle enregistre `PhilSearchFilters` ; avec AssetMapper, il associe le
répertoire de son contrôleur à `@phil/search-bundle`. Le contrôleur est déclaré dans `assets/package.json`, que
Symfony UX lit pour l'activer dans `assets/controllers.json`. Voir [Filtres](filters.md).
