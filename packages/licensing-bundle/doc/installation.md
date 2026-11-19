# Installation

## Requirements

PHP 8.5 or later with `ext-mbstring`, `phil/url`, Symfony 8.2 or later, Doctrine ORM 3.7 and Twig 3.21. The
integrations are optional, each registered only when its package or bundle is:

| Package or bundle | What it brings |
|---|---|
| DoctrineBundle | The declarations in the table `phil_licensing_declaration`, the export guard, the page. |
| TwigBundle | The license and attribution page, the Twig functions. |
| `symfony/translation` | The page and the messages in English and French. |
| `phil/ujo` | The page as a UJO (Universal JSON Output) document. |
| `phil/structured-data` | The licensing of a work as JSON-LD. |
| `phil/paged-media-bundle` | The export guard before each print that names its work, and the strict printing. |
| `symfony/form` and `symfony/validator` | The attribution form and the license finder in steps. |
| SecurityBundle | The voter `PHIL_LICENSING_EXPORT`. |

## Installing

```sh
composer require phil/licensing-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\LicensingBundle\PhilLicensingBundle::class => ['all' => true],
];
```

## The table

With DoctrineBundle, the bundle maps `LicenseDeclaration` to the table `phil_licensing_declaration`: the application's
migrations create it.

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Column | Type | What it holds |
|---|---|---|
| `id` | `CHAR(36)` | A UUID v7. |
| `subject_type`, `subject_id` | `VARCHAR(128)`, `VARCHAR(64)` | The subject; one declaration per subject, a unique key on both. |
| `license` | `VARCHAR(64)` | The code of the license in the catalog. |
| `notice`, `reserved_material`, `product_identity`, `designated_content`, `license_text` | `TEXT`, nullable | What the license asks the work to declare. |
| `attributions` | JSON | The attributions, in order. |
| `declared_at`, `changed_at` | `DATETIME_IMMUTABLE` | When it was declared, and last changed. |
| `version` | `INTEGER` | The optimistic lock of Doctrine. |

Without DoctrineBundle, the application names its own repository: see [Configuration](configuration.md).
