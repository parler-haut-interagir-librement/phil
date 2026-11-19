<!-- phil-dev source-sha256=8151bce0b49f923a214b3838cbf3d4b9ded67940a8d82a6b094fb310ec051c64 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring`, `phil/url`, Symfony 8.2 ou plus récent, Doctrine ORM 3.7 et Twig 3.21.
Les intégrations sont facultatives, chacune enregistrée seulement lorsque son paquet ou son bundle est présent :

| Paquet ou bundle | Ce qu'il apporte |
|---|---|
| DoctrineBundle | Les déclarations dans la table `phil_licensing_declaration`, la garde d'exportation, la page. |
| TwigBundle | La page de licence et d'attribution, les fonctions Twig. |
| `symfony/translation` | La page et les messages en anglais et en français. |
| `phil/ujo` | La page sous forme de document UJO (Universal JSON Output). |
| `phil/structured-data` | La licence d'une œuvre en JSON-LD. |
| `phil/paged-media-bundle` | La garde d'exportation avant chaque impression qui nomme son œuvre, et l'impression stricte. |
| `symfony/form` et `symfony/validator` | Le formulaire d'attribution et l'assistant de licence par étapes. |
| SecurityBundle | Le voter `PHIL_LICENSING_EXPORT`. |

## Installer

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

## La table

Avec DoctrineBundle, le bundle associe `LicenseDeclaration` à la table `phil_licensing_declaration` : les migrations de
l'application la créent.

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Colonne | Type | Contenu |
|---|---|---|
| `id` | `CHAR(36)` | Un UUID v7. |
| `subject_type`, `subject_id` | `VARCHAR(128)`, `VARCHAR(64)` | Le sujet ; une déclaration par sujet, avec une clé unique sur les deux. |
| `license` | `VARCHAR(64)` | Le code de la licence dans le catalogue. |
| `notice`, `reserved_material`, `product_identity`, `designated_content`, `license_text` | `TEXT`, nullable | Ce que la licence demande à l'œuvre de déclarer. |
| `attributions` | JSON | Les attributions, dans l'ordre. |
| `declared_at`, `changed_at` | `DATETIME_IMMUTABLE` | Le moment de la déclaration et celui du dernier changement. |
| `version` | `INTEGER` | Le verrou optimiste de Doctrine. |

Sans DoctrineBundle, l'application nomme son propre dépôt : voir [Configuration](configuration.md).
