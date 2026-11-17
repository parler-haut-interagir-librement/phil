# Idempotency

A controller marked `#[Idempotent]` requires an `Idempotency-Key` header, and runs once per key: a retry gets the
first response again. The listener runs the guard of `phil/idempotency` around the controller; its keys,
fingerprints, states and stores are documented there.

```php
use Phil\ApiContractBundle\Infrastructure\Http\Idempotent;

#[Route('/api/orders', methods: ['POST'])]
#[Idempotent]                 // or #[Idempotent(ttl: 3600)], at least 1
public function create(Request $request): Response
```

| The request | The answer |
|---|---|
| No key, or a key that cannot be read | `400` problem; for an unreadable key, a fixed `detail` saying what a key is, never the exception's message |
| A new key | The controller runs; its response is kept, unless it is a `5xx` or streamed — then the key is marked failed, so that the client may retry |
| A key already answered, with the same method, path, query and body | The kept response, with `Idempotent-Replayed: true`; the controller does not run |
| A key already used with another request | `422` problem, whether the first request has finished, is running or has failed |
| A key whose first request is still running | `409` problem with `Retry-After`: the seconds left on its reservation |
| A new key whose request ended after its reservation expired and another request reserved the key | `409` problem in place of the controller's answer, which is not kept: the key answers for the other request. A `5xx` of the controller is left as it is |
| A key whose first request failed, with the same request | The controller runs again |

Every refusal is an RFC 9457 problem, `application/problem+json`. The two refusals follow the IETF draft of the header:
`422` for a key reused with another request, a mistake the client must correct, since retrying it never succeeds;
`409` for a key whose first request is still running, which the client retries after `Retry-After`.

A key is reserved for `idempotency.reservation_ttl` seconds (300 by default, or the key's own retention if
shorter) while its first request runs, then kept for its retention, `idempotency.ttl` or the attribute's `ttl`, once
answered or failed. A process that dies before its response — a fatal error, a timeout, a killed worker — leaves
nothing to complete the key, so its retries get `409` until the reservation expires, not for the whole retention.
Keep the reservation longer than the slowest request: a reservation that expires while its request still runs lets a
retry run beside it. The late request then never overwrites the retry's outcome — each reservation carries a token
of its own, which `phil/idempotency` checks before it writes — and its client gets a `409` problem titled
`IdempotencyListener::RESERVATION_LOST`, so that a retry reads the outcome of the request that holds the key.

Only the status, the body and `idempotency.replayed_headers` are kept.

## Access checks before the replay

The listener runs on `kernel.controller_arguments`, at priority `IdempotencyListener::PRIORITY` (-10100), after
Symfony has resolved the controller's arguments and dispatched the listeners of its attributes, at -10000:
`#[IsGranted]`, `#[IsCsrfTokenValid]`, `#[IsSignatureValid]`, `#[RateLimit]`, and the value resolvers of the
arguments, such as an entity loaded under the tenant's filter. They decide on every request, a replay included:

- a caller who lost a permission while the key was kept is refused by the access check, never answered with the
  stored body;
- a request refused by an access check reads no key and reserves none: once granted, the same key runs the
  controller.

A replay takes the controller's place with the original's attributes, so that the listeners of the response that read
them — `#[Cache]`, the deprecation of `#[DeprecatedEndpoint]` — still apply, and the profiler names the real
controller.

## The scope of a key

A key is scoped to its caller: two requests share a key only when they come from the same tenant, the same API client
and the same user. `CallerScopeResolver` builds the scope from:

| Part | From | Without it |
|---|---|---|
| The tenant | `IdempotencyTenantResolver`: by default `ActiveTenantResolver`, the active tenant of `phil/tenancy-bundle`, which that bundle resolves and checks against the tenants the account may reach | No tenant part |
| The client | `IdempotencyClientResolver`: by default `TokenAttributeClientResolver`, the attribute `idempotency.client_attribute` (`client_id`) of the security token | No client part |
| The user | The identifier of the security token's user | An anonymous caller, below |

The scope is `caller:` and the SHA-256 of the parts present, each named and length-prefixed: the table holds no user
identifier, and a tenant named like a client never shares its keys.

A caller that is neither a user nor a client cannot be told apart from another one. `idempotency.anonymous` decides:

- `refuse`, the default: a `403` problem titled `CallerScopeResolver::ANONYMOUS`; no key is read or reserved;
- `ip`: the scope is the client's address, within the tenant. Every anonymous caller behind one address — a corporate
  proxy, a carrier's NAT, a shared Wi-Fi — shares its keys: one of them who sends the same key, path and body as
  another is answered with the other's response. Choose it only for endpoints whose response holds nothing about the
  caller, and set Symfony's `trusted_proxies`, or every caller behind the proxy has its address.

### Several tenants

An application that serves several tenants from one store must put the tenant in the scope. With
`phil/tenancy-bundle`, nothing is to be done: its active tenant is read. An application that resolves its tenants
another way aliases `IdempotencyTenantResolver` to a service that returns the tenant it verified — never a host or a
header the client chose, which another tenant's client can send too:

```php
final readonly class TenantResolver implements IdempotencyTenantResolver
{
    public function __construct(private CurrentTenant $tenant) {}

    public function tenant(Request $request): ?string
    {
        return $this->tenant->get()?->reference();
    }
}
```

```yaml
services:
    Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver: '@App\Tenancy\TenantResolver'
```

A tenant with a store of its own — a database per tenant — is already apart; the tenant in the scope does no harm.

### API clients

An authenticator of access tokens that knows the client sets it on the token, `$token->setAttribute('client_id', …)`,
or under the name `idempotency.client_attribute` gives. An application whose clients are known another way aliases
`IdempotencyClientResolver`; one that names its callers altogether differently aliases `IdempotencyScopeResolver`.

## Threat model

| Threat | Answer |
|---|---|
| A caller replays another caller's response by guessing or reusing its key | The scope holds the tenant, the client and the user: a key is never shared across them |
| A tenant's client is answered with another tenant's response on a shared store | The verified tenant is in the scope; the fingerprint covers the method, path, query and body, never the host, so the tenant must come from the scope |
| A user who lost a permission reads the stored body by replaying the key | The access checks of the controller and of its arguments run before every replay |
| A refused request blocks the key for the permitted retry | A refused request reserves no key |
| Two keys differing only by case or accent name one row on MySQL or MariaDB | The schema listener builds the table for the connection's platform: `scope` and `idempotency_key` in `utf8mb4_bin` |
| Anonymous callers behind one address share keys | Refused by default; `anonymous: ip` is an explicit choice |
| The table discloses who called | The scope is a SHA-256; only the fingerprint of the request is kept, with the response |

What stays the application's: the response kept is replayed as it was, so an endpoint whose response depends on
something else than the caller, the key and the request — a resource that changed, a price — answers with the first
one until the key expires; and a firewall or `access_control` rule runs before any controller, replay or not.

## The store

With DoctrineBundle, the keys are in the table `phil_idempotency` of `DbalIdempotencyStore`, on
`idempotency.connection`: scope, key, fingerprint, state, the kept response and when it expires, with a primary key on
scope and key. The bundle adds the table to the schema of that connection, built for its platform: on MySQL and MariaDB,
`scope` and `idempotency_key` take `utf8mb4_bin`, so that they compare byte for byte. A reservation is one atomic
insert; an expired key is never read. `DbalIdempotencyStore::purge()` forgets the expired keys; run it from a scheduled
task.

With `idempotency.cache_pool`, the keys are in that pool instead, through `CacheIdempotencyStore`, each reservation
under a lock of `idempotency.lock_factory`. The pool and the lock store must be shared by every server that answers
the same API.

```yaml
# config/packages/phil_api_contract.yaml
phil_api_contract:
    idempotency:
        cache_pool: cache.idempotency
```

Without a store — no DoctrineBundle, no cache pool and no alias of `IdempotencyStore` —, an `#[Idempotent]`
controller fails with a `LogicException` that says so.

## Upgrading from 0.2

- **Anonymous callers are refused.** An `#[Idempotent]` endpoint called without a user or a client answers `403`.
  Authenticate its callers, or set `idempotency.anonymous: ip` knowing that callers behind one address share keys.
- **Scopes changed.** A scope is now `caller:` and a SHA-256, with the tenant and the client: keys recorded by 0.2,
  `user:…` and `ip:…`, are no longer found. A retry that arrives across the deployment, within the retention, runs its
  controller again. Deploy when the clients are not retrying, or keep the retention of 0.2's keys in mind.
- **The listener moved.** `IdempotencyListener::onController(ControllerEvent)` is now
  `onControllerArguments(ControllerArgumentsEvent)`, on `kernel.controller_arguments` at -10100. A request refused by
  an access check no longer reserves its key, and a replay runs the access checks first.
- **The table on MySQL and MariaDB.** A table generated by 0.2 from the schema listener compares `scope` and
  `idempotency_key` with the connection's default collation. `doctrine:migrations:diff` now writes the migration that
  changes both columns to `utf8mb4_bin`; run it.
- **A resolver of your own** that implemented `IdempotencyScopeResolver` is kept as it is; consider putting the tenant
  and the client in its scope.
