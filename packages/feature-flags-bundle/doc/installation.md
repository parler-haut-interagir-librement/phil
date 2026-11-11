# Installation

This page takes an application from nothing to a working bundle: requirements, Composer, the bundle class, the
database tables, the admin routes and the firewall in front of them. The full list of options is in
[configuration.md](configuration.md).

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Declared as `>=8.5`; the code uses typed class constants, `readonly` classes and `#[Override]`. |
| `ext-mbstring` | any | Reasons, actors, descriptions and owners are checked as UTF-8 and measured in characters. |
| `ext-hash` | bundled with PHP | The stable buckets of rollouts and experiments use the `murmur3a` algorithm of `hash()`. It is always compiled in since PHP 7.4, so it is not declared. |
| `doctrine/dbal` | `^4.5` | The database store and its schema. |
| `doctrine/orm` | `^3.7` | The schema listener hooks into the ORM's `postGenerateSchema` event. |
| `phil/circuit-breaker`, `phil/circuit-breaker-bundle` | `^0.1@dev` | The `CircuitOverride` port the force-open flags implement, and the circuits whose keys declare those flags. |
| `phil/ddd-bundle` | `^0.1@dev` | The `InvalidValue` and `DomainError` exceptions of the domain. |
| `phil/enums` | `^0.3@dev` | `AsValuableEnumTrait` on `FlagKind` and `TargetingAttribute`, whose `values()` writes the error messages and the kinds of the admin form. |
| `psr/clock` | `^1.0` | Every timestamp (audit lines, expiry) comes from a PSR-20 clock. |
| `symfony/config`, `console`, `dependency-injection`, `http-foundation`, `http-kernel`, `routing` | `^8.2` | Configuration tree, listing command, wiring, admin controller, controller attribute, route condition. |
| `twig/twig` | `^3.21` | The admin templates and the `#[AsTwigFunction]` functions. |

**Doctrine DBAL 4.5.** `FlagTables` builds its tables with `Schema::edit()` and the table, column and primary
key editors, and `FlagSchemaListener` hands the schema back with `GenerateSchemaEventArgs::setSchema()`, which
throws unless `Schema::edit()` exists (DBAL 4.5 and later), which is why the bundle requires `^4.5`.

The bundle registers its services with tags that other bundles act on, so in practice the application also has:

| Bundle | Needed for |
|---|---|
| `symfony/framework-bundle` | The default `clock` service (with `symfony/clock`), the `router` service the admin uses, `kernel.reset`, `console.command`, `kernel.event_listener` and `routing.condition_service` tags. |
| `symfony/twig-bundle` | The `twig` service of the admin, and the `twig.attribute_extension` / `twig.runtime` tags of the Twig functions. |
| `doctrine/doctrine-bundle` | Only with the database store: the `doctrine.dbal.<name>_connection` service and the `doctrine.event_listener` tag. |
| `symfony/expression-language` | Only for route conditions (`condition: "service('feature_flags')…"`). |

Databases: the database store is tested on SQLite, MySQL 8 and PostgreSQL.

## Install with Composer

```bash
composer require phil/feature-flags-bundle
```

The bundle requires `phil/ddd-bundle`, `phil/circuit-breaker` and `phil/circuit-breaker-bundle` at a development
stability (`^0.1@dev`). Until they are published, the application declares a `path` or `vcs` repository for them and
accepts `@dev` for these packages.

## Register the bundle

With Symfony Flex the bundle is added to `config/bundles.php` for you; otherwise add it by hand, after the
bundles it relies on:

```php
// config/bundles.php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],   // database store only
    Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle::class => ['all' => true],   // circuits forced open by a flag
    Phil\FeatureFlagsBundle\PhilFeatureFlagsBundle::class => ['all' => true],
];
```

Without `PhilCircuitBreakerBundle`, no circuit is declared and no force-open flag either; the flags work alike.

## The smallest configuration

The configuration key is `phil_feature_flags`. With no adapter at all, flags declared in YAML and an empty context:

```yaml
# config/packages/phil_feature_flags.yaml
phil_feature_flags:
    context_resolver: phil_feature_flags.context_resolver.anonymous
    flags:
        new-checkout: true
```

Without `context_resolver`, the bundle expects a service named after the
`Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver` interface: implement it in `src/` (with
autowiring, a single implementation is aliased to the interface) — see [extension-points.md](extension-points.md).

## The database store and its migration

Enable the store to keep active states and admin-created flags in the database:

```yaml
phil_feature_flags:
    doctrine:
        connection: default     # a DBAL connection name of DoctrineBundle
```

The store owns two tables, `phil_feature_flags_definition` and `phil_feature_flags_state`
([database-store.md](database-store.md) describes their columns). The bundle does not ship a migration:
`FlagSchemaListener` adds both tables to the schema the ORM generates for that connection, so the usual tools see
them:

```bash
bin/console doctrine:migrations:diff        # writes the migration that creates the two tables
bin/console doctrine:migrations:migrate
bin/console doctrine:schema:validate        # does not ask to drop them
```

The listener only runs when the ORM's schema tool runs for an entity manager on that same connection. An
application without an entity manager on that connection creates the tables from the same definition:

```php
use Doctrine\DBAL\Schema\Schema;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagTables;

foreach (FlagTables::configure(new Schema())->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

## The toggle

Changing a flag at runtime needs the audited toggle, which needs a state store (the database store gives one)
and two adapters of the application, an authorizer and an auditor:

```yaml
phil_feature_flags:
    doctrine: true
    toggle:
        authorizer: App\Flags\PermissionAuthorizer
        auditor: App\Flags\AuditTrailAuditor
```

See [toggles-and-edits.md](toggles-and-edits.md) and [extension-points.md](extension-points.md).

## The admin routes

Enable the screens (they need the toggle) and name the service that says who the operator is:

```yaml
phil_feature_flags:
    admin:
        actor_provider: App\Flags\OperatorProvider
```

Then import the bundle's routes under a prefix of your choice:

```yaml
# config/routes/phil_feature_flags.yaml
phil_feature_flags_admin:
    resource: '@PhilFeatureFlagsBundle/config/routes/admin.php'
    prefix: /admin/flags
```

The five routes it declares are listed in [admin.md](admin.md).

## The firewall in front of the admin

The bundle does not authenticate anyone. Put the prefix behind the application's firewall and require a role:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/flags, roles: ROLE_FLAG_OPERATOR }
```

Inside, the `ActorProvider` names the operator (a screen answers 403 when it returns `null`), the authorizer
decides each change, and every form post is checked as same-origin. Details in [admin.md](admin.md).

## Check the installation

```bash
bin/console debug:config phil_feature_flags
bin/console phil:feature-flags:list
```

The listing shows every flag the configuration and the database declare, with its state and source
([console.md](console.md)).
