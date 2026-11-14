# Installation

## Requirements

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The declared floor. `hash_hmac()`, `hash_equals()` and `random_bytes()` are part of every PHP build. |
| `phil/ddd-bundle` | `^0.1@dev` | `Salt` and `Commitment` throw its `Phil\DddBundle\Domain\Exception\InvalidValue`, so an application catches the audit bundle's value errors with the same `DomainError` as its own. |
| `psr/clock` | `^1.0` | The time a row is recorded and an anchor taken at; the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed. |
| `symfony/dependency-injection` | `^8.2` | The bundle class extends `AbstractBundle`. |
| `symfony/config` | `^8.2` | Imported nowhere in the bundle, but `AbstractBundle` implements an interface from `symfony/config`, which `symfony/dependency-injection` requires only in development: without it the bundle class cannot load when the bundle is installed on its own. |

Optional, each registered only when it is installed or configured ([integrations.md](integrations.md)):

| Package | For |
|---|---|
| `doctrine/dbal`, `doctrine/orm` | The trail's table, its schema listener and the reader of the log |
| `symfony/console` | `phil:audit:anchor`, `phil:audit:verify` and `phil:audit:feed` |
| `symfony/messenger` | The dispatch of each recorded row, `dispatch_bus` |
| `symfony/security-core` | The current account in the rows the bundle records |
| `symfony/workflow` | The transitions of `Auditable` subjects, `workflow_segment` |
| `symfony/http-client`, `phil/url` | The facts sent to an OpenTelemetry collector, `feed.otlp`; its endpoint read by `phil/url` |
| `phil/tenancy-bundle` | The tenant each row is stamped with, and the reads held to it |

## Installing

```bash
composer require phil/audit-bundle:^0.1@dev
```

The package declares `"minimum-stability": "dev"` and a branch alias `dev-main` → `0.1.x-dev`. Until
`phil/ddd-bundle` is published, an application resolves both from a repository it declares (a `path` or `vcs`
repository).

## Registering the bundle

With Symfony Flex the bundle is registered automatically. Otherwise add it to `config/bundles.php`:

```php
return [
    // ...
    Phil\DddBundle\PhilDddBundle::class => ['all' => true],
    Phil\AuditBundle\PhilAuditBundle::class => ['all' => true],
];
```

Registered, it wires the trail on a Doctrine connection, its reader and the commands, as
[configuration.md](configuration.md) lists. The value objects and ports are plain classes you can use without
registering the bundle at all.

## Read this before using it in production

The trail holds up against an administrator with access to the database **only when its anchors are written to
append-only storage that administrator cannot rewrite**. Without anchors, a full rewrite of the chain goes
undetected. See [anchoring.md](anchoring.md).

The canonical serialization is **yours to write and freeze before your first anchored row**. See
[serialization-door.md](serialization-door.md).
