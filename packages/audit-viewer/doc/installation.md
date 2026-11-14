# Installation

## Requirements

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The declared floor. |
| `phil/audit-bundle` | `^0.1@dev` | The services the pages call: `AuditLog`, `ChainStatus`, and `AuditTrail` for the export. |
| `phil/ddd-bundle` | `^0.1@dev` | The `InvalidValue` a filter that does not read throws. |
| `easycorp/easyadmin-bundle` | `^5.8` | The pages are admin routes of every dashboard, in its layout. |
| `symfony/security-core`, `symfony/security-csrf` | `^8.2` | The voter, the authorization checker and the export's token. |
| `symfony/http-foundation`, `symfony/http-kernel` | `^8.2` | The controller's requests, responses and errors. |
| `symfony/translation` | `^8.2` | The labels of the menu, as translatable messages. |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | The bundle class extends `AbstractBundle`. |
| `psr/clock` | `^1.0` | The name of an export file. |
| `twig/twig` | `^3.21` | The pages. |

## Installing

```bash
composer require phil/audit-viewer:^0.1@dev
```

The package declares `"minimum-stability": "dev"` and a branch alias `dev-main` → `0.1.x-dev`.

## Registering the bundle

With Symfony Flex the bundle is registered automatically. Otherwise add it after `phil/audit-bundle` and EasyAdmin in
`config/bundles.php`:

```php
return [
    // ...
    EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['all' => true],
    Phil\AuditBundle\PhilAuditBundle::class => ['all' => true],
    Phil\AuditViewer\PhilAuditViewerBundle::class => ['all' => true],
];
```

EasyAdmin's layout uses the Twig components and the Twig extras: an application with EasyAdmin registers
`TwigComponentBundle` and `TwigExtraBundle`, as Flex does.

## What `phil/audit-bundle` must have

- A Doctrine connection (`phil_audit.connection`): the pages read the trail's table through `DbalAuditLogReader`.
- A serializer (`phil_audit.serializer`) for the export: without an `AuditTrail` the export cannot be recorded, so it
  is not offered and its route answers `404`.
- A `PayloadReader`, optionally, aliased by the application: a row's payload is then shown as its fields rather than
  as the bytes stored ([extension-points.md](extension-points.md)).
- An `AuditActor` on the user class, optionally, so that an export names who made it
  ([access-and-export.md](access-and-export.md)).

## Routes

EasyAdmin generates the routes of the pages for every dashboard; nothing is imported. Under a dashboard whose route is
`admin` at `/admin`, the log is `admin_audit_log` at `/admin/audit` ([pages.md](pages.md)).
