# Installation

## Requirements

PHP 8.5 or later with `ext-mbstring`, Symfony 8.2 with FrameworkBundle, SecurityBundle, Forms and TwigBundle, and
Doctrine DBAL 4 through DoctrineBundle. With the ORM, the tables join the schema it generates; with Messenger, the
allocation runs through the bus; with Symfony UX Twig Component, the components are registered; with Console, the
import command; with phil/privacy-bundle, the export and the erasure.

## Installing

```sh
composer require phil/shifts-bundle
```

The Flex recipe registers the bundle, writes `config/packages/phil_shifts.yaml` and imports the routes under
`/shifts`. Without Flex, register the bundle and import the routes yourself:

```php
// config/bundles.php
return [
    // ...
    Phil\ShiftsBundle\PhilShiftsBundle::class => ['all' => true],
];
```

```yaml
# config/routes/phil_shifts.yaml
phil_shifts:
    resource: '@PhilShiftsBundle/config/routes.php'
    prefix: /shifts
```

## Security and CSRF

The pages need a firewall that knows the person signed in, and Symfony's CSRF protection and Forms:

```yaml
# config/packages/framework.yaml
framework:
    csrf_protection: true
    form: true
```

The container refuses to build when the pages are on without them. The coordinators hold `ROLE_SHIFTS_COORDINATOR`,
or the role the configuration names; the role hierarchy of the application counts.

## The tables

With the ORM, the bundle adds its tables to the schema it generates:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Without the ORM, create them from `ShiftsTables::configure()`, described in [Storage and privacy](storage-and-privacy.md).

## A first shift

A coordinator opens `/shifts/manage/new`, or imports a plan:

```sh
php bin/console phil:shifts:import plan.json
```

```json
{"format": "phil-shifts/1", "shifts": [{"id": "desk-sat-am", "title": "Info desk", "kind": "fixed",
  "starts_at": "2026-11-28T09:00:00-05:00", "ends_at": "2026-11-28T12:00:00-05:00", "capacity": 2, "reserve": 3}]}
```

Volunteers find it at `/shifts/`, or on any page through the component:

```twig
<twig:PhilShifts:ShiftCard id="desk-sat-am" />
```
