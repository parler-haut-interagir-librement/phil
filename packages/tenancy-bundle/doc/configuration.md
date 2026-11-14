# Configuration reference

The extension alias is `phil_tenancy`. Every key is optional; an unknown key stops the kernel with
`InvalidConfigurationException` rather than being ignored.

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    # Where the chosen tenant is remembered: "session", or the id of an ActiveTenantStore service.
    store: session
    # The id of the ReachableTenantsProvider service. Without it, nothing that resolves a tenant is registered.
    reachable_tenants: App\Tenancy\EntitlementsReachableTenants
    # Registers the phil_tenancy filter, enabled, on the default entity manager.
    doctrine_filter: true
    request:
        enabled: true
        # The route an account that must choose a tenant is sent to; null to carry on in no tenant.
        selector_route: tenant_select
        # Routes resolved in no tenant, such as the login page.
        ignored_routes: [login, login_check]
    # Gives every command a --tenant option.
    console: true
    # The tenant each host serves, entered before the firewall.
    hosts:
        shop.example: general
        rose.example: rose
    # The other names of the hosts, each the canonical host it stands for.
    host_aliases:
        www.rose.example: rose.example
    # Whether a request for an alias is sent to its canonical host.
    redirect_host_aliases: true
    # The shops among the tenants, and the shops each one includes.
    shops:
        general: { includes: [rose] }
    # Sessions scoped to the tenant their accounts signed in on; off by default.
    authentication:
        enabled: false
        user_providers: [security.user.provider.concrete.members]
        account_sharing: null
        shared_sessions: false
        shared_tenants: [[fair, fair-backstage]]
        # Request matchers for the firewalls of security.yaml: phil_tenancy.request_matcher.<name>.
        tenant_firewalls:
            backstage: [fair-backstage]
        # User providers chosen for each tenant: phil_tenancy.user_provider.<name>.
        tenant_user_providers:
            members:
                tenants: { rose: security.user.provider.concrete.rose_members }
                default: security.user.provider.concrete.staff
    # How each tenant's mail leaves.
    mailer:
        settings: null
        platform_sender: 'Shops <mail@platform.example>'
        tenants:
            rose: { transport: rose_smtp, sender: 'Rose <hello@rose.example>' }
            fair: { name: Summer Fair, reply_to: fair.organisers@mail.example }
    # cache.app, and every pool built on it, scoped to the active tenant.
    cache:
        enabled: true
        global_pools: [app.exchange_rates]
    # The row-level security of PostgreSQL, behind the Doctrine filter; off by default.
    row_level_security:
        enabled: false
        connection: default
        entity_manager: default
        setting: phil.tenant_id
        policy: phil_tenant_isolation
```

| Key | Default | Effect |
|---|---|---|
| `store` | `session` | `session` registers `SessionActiveTenantStore`, which keeps the choice in the application's own Symfony session; any other value is the id of the application's `ActiveTenantStore`, aliased to the port |
| `reachable_tenants` | `null` | The id of the application's `ReachableTenantsProvider`, aliased to the port. Without it, `ActiveTenant`, the switch, the request listener and the Twig functions are not registered |
| `doctrine_filter` | `true` | Prepends `doctrine.orm.filters.phil_tenancy` with `enabled: true` when DoctrineBundle is registered; `false` leaves the filter to the application (several entity managers, a filter enabled later) |
| `request.enabled` | `true` | Registers `ResolveActiveTenant` on `kernel.request`, after the router and the firewall |
| `request.selector_route` | `null` | Where `Outcome::SelectionRequired` redirects, with the requested page as `target` on a safe request; `null` lets the request carry on in no tenant |
| `request.ignored_routes` | `[]` | Routes the listener does not resolve: they run in no tenant. The selector route and `phil_tenancy_switch` are always added |
| `console` | `true` | Registers the `--tenant` option, when `symfony/console` is installed |
| `hosts` | `{}` | The tenant reference of each host; registers `ResolveHostTenant` before the firewall ([hosts-and-shops.md](hosts-and-shops.md)). A malformed host stops the compilation |
| `host_aliases` | `{}` | The canonical host of each alias, one of `hosts`; an alias serves the tenant of its host. An alias that is a host, listed twice or of no host stops the compilation |
| `redirect_host_aliases` | `true` | Registers `RedirectHostAlias`, which sends a request for an alias to its canonical host, permanently, before any tenant is entered |
| `shops.<shop>.includes` | `[]` | The shops whose catalogues the shop includes and takes payment for; a malformed network stops the compilation |
| `authentication.enabled` | `false` | Scopes the sessions of `user_providers`, when `symfony/security-http` is installed ([authentication.md](authentication.md)) |
| `authentication.user_providers` | `[]` | The service ids of the user providers to decorate |
| `authentication.account_sharing` | `null` | The id of the application's `AccountSharing`; by default `ConfiguredAccountSharing` |
| `authentication.shared_sessions` | `false` | Whether a session opened in no tenant, as on a central back office, is signed in on every tenant; read by `ConfiguredAccountSharing` |
| `authentication.shared_tenants` | `[]` | Groups of tenants whose accounts are signed in on every tenant of the group |
| `authentication.tenant_firewalls` | `{}` | Each name registers `phil_tenancy.request_matcher.<name>`, a `TenantRequestMatcher` of its tenants, for the `request_matcher` of a firewall. A firewall of no tenant, or a malformed reference, stops the compilation |
| `authentication.tenant_user_providers` | `{}` | Each name registers `phil_tenancy.user_provider.<name>`, a `TenantUserProviders` over the provider of each tenant (`tenants`, by reference) and the `default` of the others, for the `id` of a provider of `security.yaml` |
| `mailer.settings` | `null` | The id of the application's `TenantMailSettings`, instead of `mailer.tenants` ([mail.md](mail.md)) |
| `mailer.platform_sender` | `null` | The sender of the tenants without a domain of their own |
| `mailer.tenants.<tenant>` | `{}` | `transport`, `sender`, `name`, `reply_to`; any of them registers `TenantTransports`, when `symfony/mailer` is installed |
| `cache.enabled` | `true` | Scopes `cache.app`, and every pool built on it, to the active tenant, when `symfony/cache` is installed ([cache.md](cache.md)) |
| `cache.global_pools` | `[]` | The pools built on `cache.app` that every tenant shares, besides Symfony's own; a name that is no cache pool stops the compilation |
| `row_level_security.enabled` | `false` | Registers the session variable, its DBAL middleware, the policies and their commands ([row-level-security.md](row-level-security.md)) |
| `row_level_security.connection` | `default` | The Doctrine connection whose sessions carry the active tenant |
| `row_level_security.entity_manager` | `default` | The entity manager whose tenant-scoped entities get a policy |
| `row_level_security.setting` | `phil.tenant_id` | The session variable: two lowercase identifiers joined by a dot; another name stops the compilation |
| `row_level_security.policy` | `phil_tenant_isolation` | The policy on each tenant-scoped table: one lowercase identifier |

## What is registered

| Service | When | Role |
|---|---|---|
| `Application\TenantContext` | always; public | The tenant of the current request, message or command; reset between requests (`kernel.reset`) |
| `Infrastructure\Doctrine\DoctrineTenantActivation` | always; does nothing without Doctrine | Sets the context's tenant on the filter of every entity manager that has it enabled |
| `Infrastructure\Http\CurrentTenantValueResolver` | always | The `#[CurrentTenant]` controller argument |
| `Infrastructure\Session\SessionActiveTenantStore` | `store: session` | The remembered choice, in the session |
| `Infrastructure\Messenger\TenantMiddleware` | `symfony/messenger` installed | The middleware a bus adds to carry the tenant across transports |
| `Infrastructure\Console\TenantOption` | `console: true`, `symfony/console` installed | The `--tenant` option |
| `Domain\Shop\ShopNetwork` | always | The shops and the catalogues each one includes |
| `Application\Shop\ShopContext` | always; public | The shop of the request, its catalogues and the origin of an order |
| `Infrastructure\Http\ConfiguredHostTenants`, `ResolveHostTenant` | `hosts` or `host_aliases` set | The tenant of each host and alias, entered before the firewall; `HostTenants` and `HostAliases` aliased to `ConfiguredHostTenants` |
| `Infrastructure\Http\RedirectHostAlias` | `host_aliases` set, `redirect_host_aliases` | Each alias sent to its canonical host, before `ResolveHostTenant` and the firewall |
| `Infrastructure\Security\ConfiguredAccountSharing`, `RememberSignInTenant` | `authentication.enabled`, `symfony/security-http` installed | The shared accounts; the tenant of each login, in the session |
| `phil_tenancy.scoped_user_provider.<provider>` | `authentication.enabled`, one per user provider | `TenantScopedUserProvider`, decorating the provider |
| `phil_tenancy.request_matcher.<name>` | `authentication.enabled`, one per tenant firewall | `TenantRequestMatcher` |
| `phil_tenancy.user_provider.<name>` | `authentication.enabled`, one per entry of `tenant_user_providers` | `TenantUserProviders`; each tenant's provider built only when asked for |
| `Infrastructure\Mailer\ConfiguredTenantMailSettings` | `mailer.tenants` set | The mail settings of each tenant |
| `Infrastructure\Mailer\TenantTransports` | `mailer.tenants` or `mailer.settings` set, `symfony/mailer` installed | Decorates `mailer.transports` |
| `<pool>.phil_tenancy` | `cache.enabled`, `symfony/cache` installed; one per scoped pool | `Infrastructure\Cache\TenantScopedPool` or `TenantScopedTagAwarePool`, decorating `cache.app`, `cache.app.taggable` and each pool built on them; tagged `phil_tenancy.activation` |
| `Application\ActiveTenant` | `reachable_tenants` set; public | `resolve()`, `canReach()`, `switchTo()`, `leave()` |
| `Infrastructure\Http\SwitchActiveTenant` | `reachable_tenants` set | The controller of the `phil_tenancy_switch` route |
| `Infrastructure\Http\ResolveActiveTenant` | `reachable_tenants` set, `request.enabled` | The request listener |
| `Infrastructure\Twig\TenancyExtension` | `reachable_tenants` set, `twig/twig` installed | `active_tenant()`, `reachable_tenants()` |
| `Infrastructure\Doctrine\RowLevelSecurity\TenantSession` | `row_level_security.enabled` | The activation that sets the session variable on each open connection |
| `Infrastructure\Doctrine\RowLevelSecurity\TenantSessionMiddleware` | `row_level_security.enabled` | The DBAL middleware of the connection, tagged `doctrine.middleware` |
| `Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityPolicies` | `row_level_security.enabled` | The tenant-scoped tables and their statements |
| `Infrastructure\Console\RowLevelSecurityPoliciesCommand`, `RowLevelSecurityCheckCommand` | `row_level_security.enabled`, `symfony/console` installed | `phil:tenancy:rls:policies`, `phil:tenancy:rls:check` |

Every service implementing `Application\Contract\TenantActivation` is tagged `phil_tenancy.activation` by
autoconfiguration and called by the context.

## What the application still writes

| What | Why | Where documented |
|---|---|---|
| The reachable tenants | Only the application knows where its entitlements come from | [extension-points.md](extension-points.md) |
| The selector page | Its rendering, and whether entering a tenant asks for a fresh authentication | [resolution.md](resolution.md) |
| The switch route import | Its path and prefix are the application's | [integrations.md](integrations.md) |
| The middleware on its buses | Only the application knows its buses | [integrations.md](integrations.md) |

## Constants

| Constant | Value | Use |
|---|---|---|
| `ActiveTenantFilter::NAME` | `phil_tenancy` | The name the filter is registered, enabled and disabled under |
| `ActiveTenantFilter::PARAMETER` | `tenant` | The filter parameter `activate()` sets; set it only through `activate()` |
| `TenantId::MAX_LENGTH` | `255` | Longest tenant reference, in bytes |
| `SwitchActiveTenant::ROUTE` | `phil_tenancy_switch` | The switch route's name |
| `SessionActiveTenantStore::KEY` | `_phil_tenancy.active_tenant` | The session attribute holding the choice |
| `RowLevelSecurityConfig::DEFAULT_SETTING` | `phil.tenant_id` | The default session variable |
| `RowLevelSecurityConfig::DEFAULT_POLICY` | `phil_tenant_isolation` | The default policy name |
| `TenantOption::NAME` | `tenant` | The console option |
| `PhilTenancyBundle::ACTIVATION_TAG` | `phil_tenancy.activation` | The tag of the activations |
| `TenantCacheNamespace::PREFIX` | `tenant` | The start of every tenant's sub-namespace and tag prefix: `tenant.` or `tenant-` |
| `TenantCacheNamespace::READABLE_LENGTH` | `64` | Longest reference kept readable in a sub-namespace |
| `TenantPools::MAX_TENANTS` | `32` | Most tenants' pools a scoped pool keeps at once |
| `TenantCachePass::SHARED_POOLS` | Symfony's pools built on `cache.app` | The pools every tenant shares without being listed ([cache.md](cache.md)) |
| `TenantCachePass::DECORATOR_SUFFIX` | `.phil_tenancy` | The suffix of the decorator of each scoped pool |
| `RememberSignInTenant::SESSION_KEY` | `_phil_tenancy.signed_in_on` | The session attribute holding the tenant an account signed in on |
