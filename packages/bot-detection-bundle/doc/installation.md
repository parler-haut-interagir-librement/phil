# Installation

## Requirements

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The code and its tests use PHP 8.5 syntax (`new Foo()->bar()` without parentheses). |
| `symfony/dependency-injection` | `^8.2` | `PhilBotDetectionBundle` extends `AbstractBundle`. |
| `symfony/config` | `^8.2` | Imported nowhere, but `AbstractBundle` implements an interface from `symfony/config`, which `symfony/dependency-injection` only requires in development. Without it, the bundle class cannot load. |

Nothing else: no PHP extension beyond the defaults, no HTTP, cache or metrics component. The counter adapter the
application writes brings its own dependencies ([extension-points.md](extension-points.md)).

## Installing

The bundle is in `0.1.x-dev` (`dev-main` is aliased to `0.1.x-dev`) and is not yet published on Packagist. Until
it is, declare a `path` repository to a local checkout, or a `vcs` repository:

```json
{
    "repositories": [
        { "type": "path", "url": "../phil-bundles/packages/bot-detection-bundle" }
    ]
}
```

```bash
composer require phil/bot-detection-bundle:^0.1@dev
```

The bundle's own `"minimum-stability": "dev"` and `"prefer-stable": true` only apply when it is the root package
(running its tests alone); an application needs `@dev` on the constraint, as above, or the same stability
settings.

## Registering the bundle

```php
// config/bundles.php
return [
    // ...
    Phil\BotDetectionBundle\PhilBotDetectionBundle::class => ['all' => true],
];
```

**Registering it changes nothing today**: the bundle declares no service, parameter or configuration key
([configuration.md](configuration.md)). Its classes work through Composer's autoloader alone, and its `verdict`
Stimulus controller is found by StimulusBundle from `assets/package.json`, whether or not the bundle is
registered. Registering it reserves the `phil_bot_detection` configuration alias for later versions.

A bundle that only reuses its types — `phil/analytics-bundle` uses `Verdict` and `RobotVerdictCounter` — does
not need it registered either.

## What to do next

1. Read [verdict.md](verdict.md) and [friction.md](friction.md) for what a verdict is and what it may do.
2. Write a `RobotVerdictCounter` adapter per surface ([extension-points.md](extension-points.md)).
3. Wire `VerdictHandler` on each surface that receives a verdict ([verdict-handling.md](verdict-handling.md)).
4. Place the `verdict` controller on the pages and forms that send a verdict
   ([browser-signals.md](browser-signals.md#placing-the-controller)). A request from a surface where it is not
   placed, or from a browser that blocks scripts, carries no verdict and is `Verdict::Unknown`: choose the friction
   policy with that in mind.
