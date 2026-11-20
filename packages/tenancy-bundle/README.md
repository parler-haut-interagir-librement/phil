# phil/tenancy-bundle

**Which tenant an account acts in right now, switched instantly and enforced in every query.**

phil/tenancy-bundle manages the active tenant of a multi-tenant Symfony application, whether the account chooses it
among the tenants an external identity provider lets it reach or the request's host decides it. It validates opaque
tenant references and resolves the tenant the current account acts in, from its remembered choice and the tenants it
can reach. A tenant switch is checked against that set with no round trip to the identity provider. A Doctrine filter
keeps queries inside the active tenant and fails closed. The bundle does not keep the active tenant in a token or in
the identity provider's session, decide where an account's entitlements come from, or render the selector page.

It is for applications where one account belongs to several tenants, such as two organisations or two shops. The
identity provider knows which tenants an account can reach, and its session spans every application that shares it.
The active tenant belongs to one application, so it stays there. Switching keeps working while the provider is down,
and resolution never guesses: with several tenants and no usable choice, the account selects.

It is a Symfony bundle on Doctrine ORM and `phil/ddd-bundle`. It wires the choice remembered in the application's
session, a request listener that sends an account that must choose to the selector, a same-origin switch, a
`#[CurrentTenant]` controller argument, the tenant carried across Messenger transports, a `--tenant` option on every
command, and two Twig functions. Several sites can live in one application: each host, and each alias of it, serves
its tenant, shops by domain share one checkout and one customer account, a session signed in on one tenant is not
signed in on another, a tenant can sign its members in with its own firewall and load them from its own user provider,
and each tenant's mail leaves through its own transport and sender. Each tenant reads and writes its own part of
`cache.app` and of the pools built on it, tags included. Messenger, the Console component, Twig, the Security
component, the Mailer component and the Cache component are optional. The application supplies the
reachable tenants through the `ReachableTenantsProvider` port, and the store of the choice is a port as well.

## Status

| Part | State |
|---|---|
| `TenantId`, `ReachableTenants` | Implemented and tested |
| `ActiveTenantRule`, `Resolution`, `Outcome`, `TenantNotReachable`, `NoActiveTenant` | Implemented and tested |
| `TenantScoped`, `ActiveTenantFilter`, `MisconfiguredTenantScope` | Implemented and tested; the filter is registered enabled |
| `ActiveTenant`, `TenantContext`, `TenantActivation`, `DoctrineTenantActivation` | Implemented and tested |
| `SessionActiveTenantStore` | Implemented and tested; the default store |
| `ResolveActiveTenant`, `SwitchActiveTenant`, `#[CurrentTenant]` | Implemented and tested |
| `TenantMiddleware`, `TenantStamp`, `TenantOption`, `TenancyExtension` | Implemented and tested; registered when their component is installed |
| `HostTenants`, `ConfiguredHostTenants`, `ResolveHostTenant`, `HostTenantResolved` | Implemented and tested; registered with `hosts` |
| `HostAliases`, `RedirectHostAlias` | Implemented and tested; registered with `host_aliases` |
| `ShopNetwork`, `OrderOrigin`, `ShopContext` | Implemented and tested |
| `AccountSharing`, `ConfiguredAccountSharing`, `TenantScopedUserProvider`, `RememberSignInTenant` | Implemented and tested; off until `authentication` is enabled |
| `TenantRequestMatcher`, `TenantUserProviders` | Implemented and tested; registered with `authentication.tenant_firewalls` and `tenant_user_providers` |
| `TenantMailSettings`, `ConfiguredTenantMailSettings`, `TenantMail`, `TenantTransports` | Implemented and tested; registered with `mailer` settings |
| `TenantScopedPool`, `TenantScopedTagAwarePool`, `TenantCacheNamespace`, `TenantCachePass` | Implemented and tested; `cache.app` and the pools built on it scoped when `symfony/cache` is installed |
| `ReachableTenantsProvider` | Port; the application implements it |
| `TenantPlacement`, `Placement` | Port and value object; a database bundle or the application implements the port |
| `PhilTenancyBundle` | Configuration `phil_tenancy` |

## Documentation

- [Installation](doc/installation.md): requirements, installing, registering and configuring the bundle, the
  switch route, and several entity managers.
- [Configuration](doc/configuration.md): every key, what each registers, what the application still writes, and
  the bundle's constants.
- [Integrations](doc/integrations.md): the tenant context and its activations, the request listener, the switch,
  `#[CurrentTenant]`, Messenger, the console and Twig.
- [Hosts and shops](doc/hosts-and-shops.md): the tenant of each host, the aliases of a host and their redirect, the
  shop network, the shop of a request and the origin of an order.
- [Authentication per tenant](doc/authentication.md): sessions scoped to the tenant their accounts signed in on,
  shared accounts, a firewall for each tenant and a user provider for each tenant.
- [Mail per tenant](doc/mail.md): each tenant's transport, sender and reply address.
- [Cache per tenant](doc/cache.md): `cache.app` and the pools built on it scoped to the active tenant, a
  sub-namespace and prefixed tags for each tenant, the pools every tenant shares, and the behaviour in a worker.
- [The active tenant](doc/active-tenant.md): why the active tenant belongs to the application, `TenantId` and its
  refusals, `ReachableTenants`, the `ActiveTenant` service and a request from end to end.
- [Resolution and switching](doc/resolution.md): the resolution table, the switch rule, `Resolution`, `Outcome`
  and the selector's rules.
- [The Doctrine filter](doc/doctrine-filter.md): `#[TenantScoped]`, the SQL the filter adds, failing closed,
  cross-tenant work, mapping errors and the filter's limits.
- [Row-level security](doc/row-level-security.md): the row-level security of PostgreSQL behind the filter, the
  session variable of each connection, the generated policies, the role and the check command.
- [Extension points](doc/extension-points.md): the ports method by method, the placement of a tenant's data among
  them, their invariants and example adapters.
- [Testing](doc/testing.md): how the bundle is tested, how to run its tests, and how an application tests its
  adapters and its isolation.
- [Reference](doc/reference.md): the public API by layer, one line per type, with its state.
- [Upgrading](doc/upgrading.md): from 0.1 to 0.2, flushing before a change of tenant, entities loaded again after
  `within()`, and `CrossTenantWrite`.

## Quick start

```bash
composer require phil/tenancy-bundle
```

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    reachable_tenants: App\Tenancy\EntitlementsReachableTenants # your adapter
    request:
        selector_route: tenant_select
```

```php
#[ORM\Entity]
#[TenantScoped] // the field `tenant` holds the tenant reference
class Order { /* … */ }

// Every query of the request now sees the active tenant's orders only.
public function orders(#[CurrentTenant] TenantId $tenant, OrderRepository $orders): Response { /* … */ }
```

## Requirements

PHP 8.5 or later; `doctrine/orm` 3.5 or later; `phil/ddd-bundle` 0.1; `symfony/config`,
`symfony/dependency-injection`, `symfony/http-foundation`, `symfony/http-kernel` and `symfony/routing` 8.2 or
later; `phil/url` 0.1; `psr/event-dispatcher` 1.0. `symfony/console`, `symfony/messenger`, `symfony/security-http`,
`symfony/mailer` and `twig/twig` are suggested.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
