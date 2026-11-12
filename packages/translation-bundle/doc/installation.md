# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `new` without parentheses |
| `phil/translation-memory` | Segments, segmentation, memory, glossary and XLIFF |
| `phil/ujo` | The documents translated |
| `doctrine/orm` 3.7 | The tables of segments, memory and terms |
| `twig/twig` 3.21 | The translation screen |
| `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing` 8.2 | The screen's controller and routes |
| `symfony/security-csrf` 8.2 | The stateless CSRF check of the screen's forms |
| `symfony/stimulus-bundle`, `symfony/asset-mapper`, optional | The screen's `csrf` controller, without a build step |
| `easycorp/easyadmin-bundle`, optional | The menu links to the glossary pages in an EasyAdmin back office |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The configuration and services |
| `psr/clock` | The times rows are written |

The application provides the services `clock` (Symfony's `ClockInterface`), `twig` and `router`, which the full
stack framework registers, and DoctrineBundle for the entity manager.

## Installing

```sh
composer require phil/translation-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\TranslationBundle\PhilTranslationBundle::class => ['all' => true],
];
```

## The tables

The bundle maps its rows for Doctrine itself — no `mappings` entry to write. Create the tables with the application's
migrations:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

[Storage](storage.md) describes the four tables.

## The screen

Import the routes behind the application's firewall, under a prefix of its choice:

```yaml
# config/routes/phil_translation.yaml
phil_translation:
    resource: '@PhilTranslationBundle/config/routes.php'
    prefix: /admin/translate
```

Then name the application's documents and who may translate them — [the translation screen](screen.md) shows both
services. Without them the screen and the glossary pages answer 404; the services of `Translations` work all the same.
A `Translators` service signs targets and comments and credits translators in public
([Comments and credits](collaboration.md)); machine translation stays off until a translator is named
([Machine translation](machine-translation.md)).
