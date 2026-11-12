# Installation

## Requirements

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The code uses PHP 8.5 syntax and functions (`array_any()`, `array_all()`, `new Foo()->bar()` without parentheses, typed class constants). |
| `phil/ddd-bundle` | `^0.1@dev` | Every value object refuses an invalid value by throwing `Phil\DddBundle\Domain\Exception\InvalidValue` (an `InvalidArgumentException`). |
| `phil/bot-detection-bundle` | `^0.1@dev` | `AdmissionRule` decides on the browser's `Verdict`. |
| `phil/ip-anonymizer` | `^0.1@dev` | `ClientDigester` composes its `Pipeline`, `KeyedHash` and `Truncation`, under one of its salt providers. |
| `phil/rate-limit` | `^0.1@dev` | `RateLimitGuard` refuses, in `prod`, a salt or limiter pool local to each server. |
| `symfony/dependency-injection` | `^8.2` | `PhilAnalyticsBundle` extends `AbstractBundle`. |
| `symfony/config` | `^8.2` | The configuration tree of `phil_analytics`. |
| `symfony/http-foundation` | `^8.2` | The collection endpoint reads a `Request` and answers a `Response`. |
| `symfony/rate-limiter` | `^8.2` | The default collection budget, `SymfonyCollectionRateLimiter`. |
| `psr/cache` | `^3.0` | The pools of the daily salt and of the limiter's state. |
| `psr/clock` | `^1.0` | The UTC day of a page view and of the daily jobs, from the application's `clock` service. |

`ext-mbstring` lower-cases a search term, `ext-sodium` makes the tokens of share links, and `ext-uri` reads an origin
as a browser does. `hash()` and `inet_pton()`
are part of a standard PHP build.

The default adapters use optional packages, suggested by the bundle and wired only when they are installed:

| Package | For |
|---|---|
| `doctrine/dbal` and DoctrineBundle | The default counter store, `counters.store: dbal` |
| `doctrine/orm` | The counter tables in the schema the ORM generates |
| `symfony/lock` | The first claim of the daily salt, atomic between servers |
| `symfony/routing` | Importing the route of the collection endpoint |
| `symfony/scheduler` and `symfony/messenger` | The daily jobs on the schedule `phil_analytics` |
| `phil/broken-flow-alerts` and `symfony/notifier` | The alert when a goal stops being reached |

A Symfony application with FrameworkBundle, DoctrineBundle and the ORM has all of them but the last line.

## Installing

The bundle is in `0.1.x-dev` (`dev-main` is aliased to `0.1.x-dev`) and is not yet published on Packagist, and
neither are `phil/ddd-bundle` and `phil/bot-detection-bundle`. Until they are, declare a repository that holds
the three packages, a `path` repository to a local checkout or a `vcs` repository per package:

```json
{
    "repositories": [
        { "type": "path", "url": "../phil-bundles/packages/*" }
    ]
}
```

Then require the bundle; the two sibling bundles come with it:

```bash
composer require phil/analytics-bundle:^0.1@dev
```

The bundle's own `composer.json` declares `"minimum-stability": "dev"` and `"prefer-stable": true`. That only
applies when the bundle is the root package (running its tests on its own); an application that requires it
needs `@dev` on the constraint, as above, or the same stability settings.

## Registering the bundle

Add it to `config/bundles.php`:

```php
return [
    // ...
    Phil\AnalyticsBundle\PhilAnalyticsBundle::class => ['all' => true],
];
```

Registered without configuration, the bundle wires its default adapters ([configuration.md](configuration.md)): the
counters on the `default` Doctrine connection, the daily salt and the limiter's state in `cache.app`, and the daily
retention. It collects nothing until a site is declared and the route is imported:

```yaml
# config/packages/phil_analytics.yaml
phil_analytics:
    sites:
        spring-con:
            origins: ['https://example.com']
            routes: { home: '/', talk: '/talks/{slug}' }
```

```yaml
# config/routes/phil_analytics.yaml
phil_analytics:
    resource: '@PhilAnalyticsBundle/config/routes.php'
    prefix: /analytics
```

Then create the counter tables, with a migration (`bin/console doctrine:migrations:diff`) or
`bin/console doctrine:schema:update --force` in development.

`phil/bot-detection-bundle` does not need to be registered for this bundle to work: only its `Verdict` enum is used.

## What to do next

1. Read [counters.md](counters.md) for what is stored and why nothing else is.
2. Declare the sites and point the collection script at the endpoint
   ([collection-and-admission.md](collection-and-admission.md)).
3. Replace the adapters that do not fit, such as the country lookup, by services of the application
   ([extension-points.md](extension-points.md)), and build the dashboards as described in
   [small-number-suppression.md](small-number-suppression.md).
