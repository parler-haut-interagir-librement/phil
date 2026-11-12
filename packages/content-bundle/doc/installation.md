# Installation

## Requirements

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The code uses PHP 8.5 functions (`array_first()`, `array_last()`), `array_any()`, typed class constants and `new Foo()->bar()` without parentheses. |
| `phil/ddd-bundle` | `^0.1@dev` | The surrogate identifiers of rows and routes extend its `Identifier` (UUIDv7) and are stored through its `IdentifierType`; every refusal of the domain implements its `DomainError`. |
| `doctrine/orm` | `^3.7` | The two base classes and the route are mapped with ORM attributes; the Doctrine adapters, the mapping listener and the transaction use the entity manager. |
| `doctrine/dbal` | `^4.2` | The DBAL types of locales, stages, versions and identifiers. |
| `symfony/workflow` | `^8.2` | The editorial workflow and the publication workflow are `StateMachine`s of the component. |
| `phil/locale-dimensions` | `^0.1@dev` | `Locale`, `Version`, `FallbackChain` and `DeclaredLocales`: the locale and the version of a row, and the declared chains a reader walks. |
| `phil/markup` | `^0.1@dev` | `MarkupFormat`, the ports `MarkupCompiler` and `HtmlPurifier`, their default adapters, and the readers that convert a draft into UJO. |
| `phil/ujo` | `^0.1@dev` | The JSON encoder of the UJO a published body stores. |
| `symfony/routing`, `symfony/http-foundation`, `symfony/http-kernel` | `^8.2` | The controller, the URL generator and the route configuration. |
| `twig/twig` | `^3.21` | The controller renders templates; the Twig extension and runtime. |
| `symfony/console` | `^8.2` | The `phil:content:*` commands. |
| `symfony/yaml` | `^8.2` | The `--file` option of the commands reads YAML. |
| `phil/slug` | `^0.1` | `Slug`, the address of a localized row, shared with the other bundles that keep slugs. |
| `phil/url` | `^0.1` | `Url`, a link to another site, a canonical URL, and the address the page events carry. |
| `phil/publishing` | `^0.1@dev` | `PagePublished` and `PageRetired`, the events `ContentManager` dispatches when a page goes online or offline. |
| `phil/sitemap` | `^0.1@dev` | `UrlProvider`, through which the published contents give the sitemap their pages. |
| `symfony/string` | `^8.2` | The default resource key of a root is its short class name in snake case. |
| `psr/clock` | `^1.0` | The publication date; the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed. |
| `symfony/event-dispatcher-contracts` | `^3.6` | The workflow dispatches its events through the contracts' `EventDispatcherInterface`. |
| `symfony/dependency-injection`, `symfony/config` | `^8.2` | `PhilContentBundle` is an `AbstractBundle` with a configuration tree. |

No PHP extension beyond a standard build is declared. The application needs the PDO driver of its database
(`pdo_mysql`, `pdo_pgsql` or `pdo_sqlite`); the bundle is tested on SQLite, MySQL 8 and PostgreSQL.

### Bundles the application registers

The bundle requires the libraries above, not the framework bundles. What it registers depends on the bundles the
kernel has (read from `kernel.bundles`):

| Kernel has | The bundle adds |
|---|---|
| nothing else | the pipeline, the markup adapters, the declared locales, and the publication, versioning, routing and editing services; the application provides the five persistence ports ([extension-points.md](extension-points.md)) |
| `DoctrineBundle` (`doctrine/doctrine-bundle`) | its DBAL types and ORM mappings, prepended to the `doctrine` configuration; the mapping listener; the Doctrine adapters of the five ports; the console commands |
| `DoctrineBundle` and `TwigBundle` (`symfony/twig-bundle`) | the controller, the URL generator and the Twig functions |
| `SecurityBundle` (`symfony/security-bundle`), with `raw_html_role` set | `RawHtmlRole`, which checks the trusted role whose holders write raw HTML on `security.authorization_checker` ([configuration.md](configuration.md#raw_html_role)) |
| `DoctrineBundle`, `TwigBundle` and `SecurityBundle` with the CSRF protection (`symfony/security-csrf`), with `admin.enabled` | the administration ([administration.md](administration.md)); `symfony/translation` for its labels, `easycorp/easyadmin-bundle` for its menu item |
| `DoctrineBundle` and `PhilScheduledTransitionsBundle` (`phil/scheduled-transitions-bundle`) | the schedule of publications and the resolver of its workflow handler ([publication.md](publication.md#scheduled-publication)) |
| `DoctrineBundle` and `PhilSearchBundle` (`phil/search-bundle`) | the search feed of the published contents ([search.md](search.md)) |

The controller and the Twig runtime use the `router` and `request_stack` services, and the commands run in
`bin/console`: in practice, the rendering layer needs FrameworkBundle as well. When the container has an
`event_dispatcher` service, the workflow dispatches its events through it. The publisher dates publications with
the `clock` service, which FrameworkBundle registers once `symfony/clock` is installed.

## Installing

The bundle is in `0.1.x-dev` (`dev-main` is aliased to `0.1.x-dev`) and is not yet published on Packagist,
and neither is `phil/ddd-bundle`. Until they are, declare a repository that holds both packages, a `path`
repository to a local checkout or a `vcs` repository per package:

```json
{
    "repositories": [
        { "type": "path", "url": "../phil-bundles/packages/*" }
    ]
}
```

Then require the bundle, and the framework bundles you want it to wire:

```bash
composer require phil/content-bundle:^0.1@dev phil/ddd-bundle:^0.1@dev
composer require doctrine/doctrine-bundle symfony/twig-bundle
```

The bundle's own `composer.json` declares `"minimum-stability": "dev"` and `"prefer-stable": true`. That only
applies when the bundle is the root package; an application that requires it needs `@dev` on the constraints,
as above, or the same stability settings.

## Registering the bundle

Add it to `config/bundles.php`, after DoctrineBundle and TwigBundle:

```php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Phil\DddBundle\PhilDddBundle::class => ['all' => true],
    Phil\ContentBundle\PhilContentBundle::class => ['all' => true],
];
```

Registering `phil/ddd-bundle` is optional for this bundle: it only uses its classes, not its services.

## What to write in the application

1. **A root entity and a row entity per kind of content** ([storage.md](storage.md)): the root extends
   `AbstractContentRoot`, declares an identifier in a column named `id` and names its row class; the row
   extends `AbstractDimensionContent` and implements the capabilities it needs, with their traits
   ([capabilities.md](capabilities.md)).
2. **The configuration** ([configuration.md](configuration.md)): the served locales and their fallback chains,
   the root classes, and — to serve pages — the Symfony routes of each kind and the Twig template of each
   template key.
3. **The routes** that mount `ContentController` ([routing.md](routing.md)) and **the templates** they render
   ([rendering.md](rendering.md)).

## Database schema

The bundle ships no migration. With DoctrineBundle, its mappings are part of the ORM metadata, so the
application's migration tool sees them:

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

The schema holds one table per row class (the application names it), with the columns of the dimension, the
`root_id` join column, the unique constraint `(root_id, stage, version, locale_key)` and the columns of every
capability the class implements; the route table, `phil_content_route`; and the table of working copies,
`phil_content_working_copy`. The columns are listed in
[storage.md](storage.md) and [capabilities.md](capabilities.md).

The ORM mappings are prepended to the entity manager named `default` (`doctrine.orm.mappings`); see
[configuration.md](configuration.md) for `entity_manager`.

## Routes and templates

The bundle imports no route file and ships no template. The application mounts `ContentController` on routes
of its own and lists them under `phil_content.routes`; it writes the templates and lists them under
`phil_content.templates` ([routing.md](routing.md), [rendering.md](rendering.md)).
