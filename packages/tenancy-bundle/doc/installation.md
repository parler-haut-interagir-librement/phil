# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares and is tested on |
| `doctrine/orm` | ^3.5 | `ActiveTenantFilter` is an ORM `SQLFilter` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` and `DomainError`, which the tenancy types throw and implement |
| `doctrine/persistence` | ^4.1 | The `ManagerRegistry` the Doctrine activation reads the entity managers from |
| `symfony/dependency-injection` | ^8.2 | The bundle class is an `AbstractBundle` |
| `symfony/config` | ^8.2 | The bundle's configuration tree |
| `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing` | ^8.2 | The session store, the request listeners, the switch and the `#[CurrentTenant]` argument |
| `phil/url` | ^0.1@dev | The hosts of `phil_tenancy.hosts`, read as the host of a web address |
| `psr/event-dispatcher` | ^1.0 | The `HostTenantResolved` event |

| Suggested | For |
|---|---|
| `symfony/console` | The `--tenant` option of every command |
| `symfony/messenger` | Carrying the active tenant across transports |
| `symfony/security-http` | Sessions scoped to the tenant their accounts signed in on |
| `symfony/mailer` | The transport, the sender and the reply address of each tenant |
| `twig/twig` | `active_tenant()` and `reachable_tenants()` |

The bundle needs no identity provider library: it only consumes the set of tenants an account can reach, which
the application reads from whatever entitlements it already holds.

## Installing

The bundle is not published on Packagist yet. Install it, and `phil/ddd-bundle`, from the repository that holds
them, through Composer repositories of type `path` or `vcs`:

```json
{
    "repositories": [
        { "type": "path", "url": "../path/to/packages/*" }
    ],
    "require": {
        "phil/tenancy-bundle": "^0.1@dev"
    }
}
```

Once published:

```bash
composer require phil/tenancy-bundle
```

## Registering the bundle

```php
// config/bundles.php
return [
    // …
    Phil\TenancyBundle\PhilTenancyBundle::class => ['all' => true],
];
```

The tenancy bundle uses only the domain classes of `phil/ddd-bundle` (`InvalidValue`, `DomainError`), which are
autoloaded; registering `PhilDddBundle` is not required for tenancy.

## Configuring

Point the bundle at the reachable tenants and at the selector page:

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    reachable_tenants: App\Tenancy\EntitlementsReachableTenants
    request:
        selector_route: tenant_select
        ignored_routes: [login]
```

With DoctrineBundle registered, the bundle registers the `phil_tenancy` filter enabled on the default entity
manager, so that a request in no tenant sees no tenant-scoped row rather than every tenant's. The chosen tenant is
remembered in the application's own session. Every key is in [configuration.md](configuration.md).

Then import the switch route, and add the middleware to the buses whose messages act in a tenant
([integrations.md](integrations.md)):

```php
// config/routes/phil_tenancy.php
return static function (RoutingConfigurator $routes): void {
    $routes->import('@PhilTenancyBundle/config/routes.php');
};
```

Mark each tenant-owned entity with `#[TenantScoped]` ([doctrine-filter.md](doctrine-filter.md)), write the
reachable tenants ([extension-points.md](extension-points.md)) and the selector page
([resolution.md](resolution.md)).

## Several entity managers, or no DoctrineBundle

With several entity managers, set `doctrine_filter: false` and register the filter on each manager that holds
tenant-scoped entities; the activation sets the tenant on every manager where it is enabled:

```yaml
doctrine:
    orm:
        entity_managers:
            default:
                filters:
                    phil_tenancy:
                        class: Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter
                        enabled: true
```

Without DoctrineBundle:

```php
$configuration->addFilter(ActiveTenantFilter::NAME, ActiveTenantFilter::class);
$entityManager->getFilters()->enable(ActiveTenantFilter::NAME);
```

and an activation of your own that sets the tenant on that entity manager ([integrations.md](integrations.md)).
