# Integrations

What the bundle wires around `ActiveTenant`: the tenant context, the request listener, the switch, the
`#[CurrentTenant]` argument, Messenger, the console and Twig. The tenant of each host, the shops, authentication per
tenant and mail per tenant have pages of their own: [hosts-and-shops.md](hosts-and-shops.md),
[authentication.md](authentication.md) and [mail.md](mail.md).

## The tenant context

`Application\TenantContext` holds the tenant the current unit of work acts in — a request, a message, a command.
`ActiveTenant` decides which tenant an account acts in; the context holds the decision for the code that runs
after it.

| Method | Behaviour |
|---|---|
| `current(): ?TenantId` | The active tenant, or `null` |
| `require(): TenantId` | The active tenant; throws `NoActiveTenant` when there is none |
| `enter(TenantId $tenant): void` | Makes the tenant active and calls `activate()` on every activation; when one fails, every activation is deactivated, no tenant is active, and the failure is thrown again |
| `leave(): void` | Makes no tenant active and calls `deactivate()` on every activation, all of them even when one fails; the first failure is thrown once they are all reached |
| `within(TenantId $tenant, callable $work): mixed` | Runs the work in the tenant, then returns to the tenant active before, or to none, even when the work throws or the tenant cannot be entered |

The context is left between requests (`kernel.reset`), so a long-running server never carries a tenant from one
request to the next.

### Activations

An activation follows the context: `Application\Contract\TenantActivation`, with `activate(TenantId)` and
`deactivate()`. The bundle ships one, `DoctrineTenantActivation`, which sets the tenant on the `phil_tenancy`
filter of every entity manager that has it enabled, and on leaving enables the filter afresh — a new instance,
with no tenant, that matches no tenant-scoped row. A manager whose filter is disabled or suspended for
cross-tenant work is left alone.

Add your own for anything else that follows the tenant — a cache namespace, a log context, a storage prefix. Any
service implementing the interface is tagged `phil_tenancy.activation` by autoconfiguration:

```php
final readonly class TenantLogContext implements TenantActivation
{
    public function __construct(private TenantLogProcessor $processor) {}

    public function activate(TenantId $tenant): void { $this->processor->tenant = $tenant->value; }

    public function deactivate(): void { $this->processor->tenant = null; }
}
```

## The request listener

`Infrastructure\Http\ResolveActiveTenant` runs on each main request, on `kernel.request`, with no priority of its own:
its tag places it after `router_listener`, so that the route is known, and after `security.firewall` — or
`debug.security.firewall`, its traceable replacement in debug —, so that the reachable tenants can read the
authenticated account. A listener of the application that must run before or after it names
`ResolveActiveTenant::class` in its own `before` or `after`.

| Resolution | What the listener does |
|---|---|
| `Outcome::Active` | Enters the tenant: the filter is set before the first query |
| `Outcome::SelectionRequired`, with `request.selector_route` | Redirects to the selector; on a safe request (`GET`, `HEAD`), with the requested URI as `target` |
| `Outcome::SelectionRequired`, without a selector route | Carries on in no tenant |
| `Outcome::NoneReachable` | Carries on in no tenant: the account stays signed in, and tenant-scoped entities match nothing |

Sub-requests share the main request's tenant. The selector route, `phil_tenancy_switch` and
`request.ignored_routes` are not resolved, so an account that must choose can reach them; they run in no tenant.

A request already in a tenant — one whose host serves it — is not resolved again, but a signed-in account must reach
that tenant (`ActiveTenant::canReach()`), whatever the route: otherwise the listener throws an
`AccessDeniedHttpException`, a `403`. With the reachable tenants and this listener,
[the authentication per tenant](authentication.md) lets a session opened in no tenant, on a central domain whose cookie
the hosts share, through to the host of a tenant; this check keeps it out of the tenants its account cannot reach. A
visitor who is not signed in — no token, or a token without a user — stays in the host's tenant. The listener reads
`security.token_storage` when SecurityBundle registers it; without it, nothing tells a visitor from an account, and the
reachable tenants decide for every request.

## The switch

The bundle's controller takes the selector's form: import its route, then post to it.

```php
// config/routes/phil_tenancy.php
return static function (RoutingConfigurator $routes): void {
    $routes->import('@PhilTenancyBundle/config/routes.php');
};
```

```twig
<form method="post" action="{{ path('phil_tenancy_switch') }}">
    <input type="hidden" name="target" value="{{ app.request.query.get('target') }}">
    <select name="tenant">
        {% for tenant in reachable_tenants() %}
            <option{% if tenant == active_tenant() %} selected{% endif %}>{{ tenant }}</option>
        {% endfor %}
    </select>
    <button>Switch</button>
</form>
```

| Request | Response |
|---|---|
| `POST /tenant/switch`, a reachable `tenant`, from this site | `ActiveTenant::switchTo()`, then a redirect to `target` |
| `target` missing or not a path of this site | A redirect to the site's root: `//host`, `/\host`, an absolute or a relative URL, and a target holding a control character or whitespace (browsers drop a tab or a line break, so `/<TAB>/host` would be read as `//host`) are never followed |
| A tenant out of reach, or a malformed reference | 403; the remembered choice is left as it was |
| Posted from another site | 403 |

The form is accepted from this site only: `Sec-Fetch-Site: same-origin` when the browser sends it, otherwise an
`Origin` that is the site's own. That check replaces a CSRF token for this form; a browser that sends neither
header is refused.

## `#[CurrentTenant]`

```php
use Phil\TenancyBundle\Infrastructure\Attribute\CurrentTenant;

public function invoices(#[CurrentTenant] TenantId $tenant): Response { /* … */ }

public function home(#[CurrentTenant] ?TenantId $tenant): Response { /* … */ }
```

The argument receives the context's tenant. With no tenant active, a nullable argument receives `null` and any
other is a 403.

## Messenger

Add the middleware to the buses whose messages act in a tenant:

```yaml
framework:
    messenger:
        buses:
            messenger.bus.default:
                middleware:
                    - Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware
```

| Envelope | What the middleware does |
|---|---|
| Dispatched in a tenant, without `TenantStamp` | Adds `TenantStamp` with the tenant's reference |
| Dispatched with a `TenantStamp` already | Keeps it |
| Dispatched in no tenant | Adds nothing |
| Received from a transport with a `TenantStamp` | Handles it in that tenant, then returns the worker to its own tenant |
| Received without a stamp | Handles it in the worker's tenant — usually none, where the filter fails closed |

The stamp carries the reference as a string, which every transport serializer keeps, and nothing else of the
tenant: no name, no setting, no personal data. Its constructor refuses a value that is not a valid reference. A
message handled synchronously is handled in the dispatcher's tenant.

Messages stay short and carry identifiers: a message that acts on heavy content carries the identifier of that
content, or uses the claim check of Messenger, rather than the content itself. A worker leaves the tenant after each
message, and Symfony resets the services between messages (`kernel.reset`), so no tenant is carried from one message to
the next. Mail queued in a tenant is sent in that tenant ([mail.md](mail.md)).

## The console

Every command gets a `--tenant` option, and runs in that tenant:

```bash
bin/console app:monthly-report --tenant=retail
```

A command line belongs to an operator, not to an account: the tenant is not checked against reachable tenants.
Without the option the command runs in no tenant. A command that declares its own `--tenant` keeps it, and the
bundle stays out of its way. A malformed reference stops the command with `InvalidValue`. `console: false` turns
the option off.

## Twig

| Function | Returns |
|---|---|
| `active_tenant()` | The active tenant's reference, or `null` |
| `reachable_tenants()` | The references the account can switch to, in the provider's order |

Both are registered when `twig/twig` is installed and `reachable_tenants` is configured.
