<!-- phil-dev source-sha256=807e6235f89b4693c74ec66f346faed352e0abb931f9355f630d9481642b57ff -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-intl`, `ext-mbstring` | toute version | La langue de la requête ; les noms comptés en caractères |
| `doctrine/orm` | ^3.7 | Les tables |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | Les types de formulaire |
| `phil/slug` | ^0.1 | Les *slugs* des noms d'étiquettes et les clés de catégorie |
| `phil/resource` | ^0.1 | `ResourceReference`, la ressource à laquelle une étiquette ou une catégorie est attribuée |
| `symfony/uid` | ^8.2 | Les identifiants |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et sa configuration |

```sh
composer require phil/taxonomy-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\TaxonomyBundle\PhilTaxonomyBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_taxonomy.yaml
phil_taxonomy:
    fallback_locale: en   # the language of a name when there is none in the language asked
    slugger: ~            # the service id of the slugger of names and keys; ~ for the default one
```

Avec DoctrineBundle, le bundle associe ses entités ; les migrations de l'application créent quatre tables :

| Table | Colonnes |
|---|---|
| `phil_taxonomy_tag` | `id`, `name` (100), `slug` (128, unique) |
| `phil_taxonomy_category` | `id`, `category_key` (128, unique), `parent` (suppression restreinte), `path` (420, indexé), `position` |
| `phil_taxonomy_category_translation` | `id`, `category` (suppression en cascade), `locale` (16), `name` (150), `description` ; unique sur la catégorie et la locale |
| `phil_taxonomy_assignment` | `id`, `term_type` (`tag` ou `category`), `term_id`, `resource_kind` (64), `resource_id` (255) ; unique sur les quatre, indexé sur la ressource |
