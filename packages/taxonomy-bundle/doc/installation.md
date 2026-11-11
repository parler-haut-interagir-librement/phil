# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-intl`, `ext-mbstring` | any | The request's language; names counted in characters |
| `doctrine/orm` | ^3.7 | The tables |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | The form types |
| `phil/slug` | ^0.1 | The slugs of tag names and category keys |
| `phil/resource` | ^0.1 | `ResourceReference`, the resource a tag or a category is given to |
| `symfony/uid` | ^8.2 | Identifiers |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration |

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

With DoctrineBundle, the bundle maps its entities; the application's migrations create four tables:

| Table | Columns |
|---|---|
| `phil_taxonomy_tag` | `id`, `name` (100), `slug` (128, unique) |
| `phil_taxonomy_category` | `id`, `category_key` (128, unique), `parent` (restricted on delete), `path` (420, indexed), `position` |
| `phil_taxonomy_category_translation` | `id`, `category` (cascades on delete), `locale` (16), `name` (150), `description`; unique on category and locale |
| `phil_taxonomy_assignment` | `id`, `term_type` (`tag` or `category`), `term_id`, `resource_kind` (64), `resource_id` (255); unique on all four, indexed on the resource |
