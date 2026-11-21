# Changelog

All notable changes to `phil/tenancy-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-20

In a shared database, an entity loaded for one tenant is no longer found from another
through Doctrine's identity map, a flush no longer writes another tenant's entity, and a tenant that cannot be entered
leaves no tenant half-active. Code that switches tenants with changes not yet flushed, or keeps entities loaded across
`within()`, has to change: see [Upgrading](doc/upgrading.md).

### Added

- `TenantWriteGuard`, registered on `onFlush` with `doctrine_filter`, and `CrossTenantWrite`: a flush that would insert,
  update or delete a tenant-scoped entity of another tenant than the one set on the filter is refused; a unit of work
  in no tenant, or with the filter disabled or suspended, writes as before.
- `ActiveTenantFilter::tenant()`, `TenantColumn::field()` and `EntityTenant::of()`.
- `doc/upgrading.md`: from 0.1 to 0.2.

### Changed

- A change of tenant detaches the tenant-scoped entities of every filtered entity manager: an instance loaded before
  `enter()`, `leave()` or `within()` is no longer managed afterwards, and a change made to it and not flushed before
  the switch is not written.
- A flush that writes a tenant-scoped entity of another tenant than the active one throws `CrossTenantWrite`, and
  nothing is written.

### Security

- `find()`, `getReference()` and lazy associations answered from Doctrine's identity map, before the filter: an entity
  loaded while one tenant was active was returned after `within()` another, or after leaving the tenant.
  `DoctrineTenantActivation` detaches the tenant-scoped entities of every filtered entity manager when its tenant
  changes; an entity scheduled for insertion or deletion stays, and the write guard refuses to flush it under another
  tenant.
- An activation that failed in `TenantContext::enter()` left the tenant set on the context and on the activations
  before it, and `within()` did not return to the tenant before: a failure now deactivates every activation, leaves no
  tenant active and is thrown again; `within()` returns to the tenant before; `leave()` reaches every activation even
  when one fails.

## 0.1.0-alpha - 2026-11-14

### Added

- `TenantId`, `ReachableTenants`.
- `ActiveTenantRule`, `Resolution`, `Outcome`, `TenantNotReachable`, `NoActiveTenant`.
- `ActiveTenantStore`, `ReachableTenantsProvider`, `TenantActivation` ports.
- `ActiveTenant`, registered once `reachable_tenants` is configured.
- `TenantContext` and its activations, with `DoctrineTenantActivation`.
- `SessionActiveTenantStore`, the default store, in the application's own session.
- `TenantScoped`, `ActiveTenantFilter`, `MisconfiguredTenantScope`; the filter is registered enabled on the default
  entity manager (`doctrine_filter`), and reads the `#[TenantScoped]` attribute of an entity class once and keeps its
  column.
- The `phil_tenancy` configuration: `store`, `reachable_tenants`, `doctrine_filter`, `request`, `console`,
  `row_level_security`.
- `ResolveActiveTenant`, the request listener, with the selector route and ignored routes.
- `SwitchActiveTenant` and its route `phil_tenancy_switch`, same-origin only. A `target` that holds a control
  character or whitespace, or that is not valid UTF-8, sends to the site's root: browsers drop a tab or a line break
  from a URL, so `/<TAB>/host` would leave the site as `//host`.
- `#[CurrentTenant]` and its argument resolver.
- The `active_tenant()` and `reachable_tenants()` Twig functions.
- The `--tenant` option of every command.
- `TenantMiddleware` and `TenantStamp` for Messenger; the stamp carries the tenant's reference only, and refuses a
  value that is not one.
- The row-level security of PostgreSQL, optional and off by default: `TenantSession`, which sets the active tenant in
  a session variable of each connection through `TenantSessionMiddleware`; `RowLevelSecurityPolicies` and the
  `phil:tenancy:rls:policies` command, which print the policies of the tenant-scoped tables for a migration; the
  `phil:tenancy:rls:check` command; `RowLevelSecurityConfig`, `TenantTable` and `TenantColumn`, which the filter
  reads too. Tested on a real PostgreSQL, and skipped without one.
- The tenant of each host: `phil_tenancy.hosts`, read through `phil/url`; the `HostTenants` port and
  `ConfiguredHostTenants`; `ResolveHostTenant`, before the firewall; the `HostTenantResolved` event. A request already
  in a tenant is not resolved again by `ResolveActiveTenant`.
- Host aliases: `phil_tenancy.host_aliases`, each alias the canonical host it stands for and serving its tenant; the
  `HostAliases` port, implemented by `ConfiguredHostTenants`; `RedirectHostAlias`, before `ResolveHostTenant` and the
  firewall, sends an alias to its canonical host — `301`, `308` for a method other than `GET` and `HEAD` — unless
  `redirect_host_aliases: false`.
- Shops by domain: `phil_tenancy.shops`, `ShopNetwork` (catalogues included one level deep, shared customer
  accounts), `OrderOrigin` and `ShopContext`.
- Authentication per tenant: `phil_tenancy.authentication`, `TenantScopedUserProvider`, which decorates each listed
  user provider, `RememberSignInTenant`, and the `AccountSharing` port with `ConfiguredAccountSharing`.
- Firewalls and user providers of each tenant: `authentication.tenant_firewalls`, each a `TenantRequestMatcher` named
  `phil_tenancy.request_matcher.<name>` for the `request_matcher` of a firewall; `authentication.tenant_user_providers`,
  each a `TenantUserProviders` named `phil_tenancy.user_provider.<name>`, which loads the accounts of each tenant from
  its own provider and those of the others from the default, and knows no account without either.
- Mail per tenant: `phil_tenancy.mailer`, `TenantTransports`, which decorates `mailer.transports`, the
  `TenantMailSettings` port, `ConfiguredTenantMailSettings` and `TenantMail`; a tenant without a domain of its own is
  sent from the platform's sender with its own `Reply-To`.
- `ResolveActiveTenant` refuses with a `403` a signed-in account on the host of a tenant it cannot reach, a session
  that signed in on a central domain whose cookie the hosts share included; `ActiveTenant::canReach()`. A visitor who
  is not signed in stays in the host's tenant.
- A session opened in no tenant — on a central host whose cookie the hosts share — is signed in there only: on the
  host of a tenant, `TenantScopedUserProvider` refuses it unless `authentication.shared_sessions: true`, an
  `AccountSharing` that shares it — `isShared()` takes a `null` `$signedInOn` —, or the reachable tenants and the
  request listener decide.
- `ResolveHostTenant` logs a warning, once per process, while `phil_tenancy.hosts` serves a tenant and
  `framework.trusted_hosts` is not set.
- Cache per tenant: `phil_tenancy.cache`, on by default when `symfony/cache` is installed. `TenantCachePass` decorates
  `cache.app` and every pool built on it with `TenantScopedPool`, which moves each call into the active tenant's
  sub-namespace through `withSubNamespace()`, and `cache.app.taggable` and the tag-aware pools with
  `TenantScopedTagAwarePool`, which prefixes every tag with the tenant's namespace; no tenant active is the global
  namespace. The tenant is read on each call; entering or leaving a tenant and `kernel.reset` commit what was saved
  deferred in its own tenant. Symfony's own shared pools and `cache.global_pools` stay shared; `TenantCacheNamespace`,
  `TenantPools` and `TenantTags`.
- The `TenantPlacement` port and the `Placement` value object: whether a tenant lives in the shared database or in a
  database of its own, named by a checked identifier; the bundle registers no implementation.
- Leaving the tenant enables the Doctrine filter afresh on every entity manager that knows it, one whose filter was
  disabled for cross-tenant work included.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
