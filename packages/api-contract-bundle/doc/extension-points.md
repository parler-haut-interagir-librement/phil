# Extension points

What an application implements, replaces, configures or relies on. The port `IdempotencyStore` is
`phil/idempotency`'s, and `ObservedDeprecations` is `phil/api-contract`'s, with their contracts and invariants.

## Ports

Each port is an alias that the application points at a service of its own — see
[Configuration](configuration.md#replacing-a-port).

| Port | Shipped | What the application's own does |
|---|---|---|
| `Phil\Idempotency\Contract\IdempotencyStore` | `DbalIdempotencyStore` on `idempotency.connection`, or `CacheIdempotencyStore` on `idempotency.cache_pool` | Keeps keys elsewhere under the library's invariants |
| `Phil\ApiContract\Deprecation\ObservedDeprecations` | `CacheObservedDeprecations`, on `cache.app` | Keeps the deprecations the application's clients observe elsewhere |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver` | `ActiveTenantResolver`: the active tenant of `phil/tenancy-bundle`, or none | Returns the tenant the application verified, by its own means |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyClientResolver` | `TokenAttributeClientResolver`: the token attribute `idempotency.client_attribute` | Returns the API or OAuth client the application authenticated |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver` | `CallerScopeResolver`: the tenant, the client and the user; anonymous callers refused or scoped by address | Names the caller another way altogether |

### `IdempotencyTenantResolver`

| Method | Contract |
|---|---|
| `tenant(Request $request): ?string` | The stable reference of the tenant the request acts in, once verified; never a host or a header the client chose. Null outside any tenant |

### `IdempotencyClientResolver`

| Method | Contract |
|---|---|
| `client(Request $request): ?string` | The authenticated client that sent the request; null when it names none |

### `IdempotencyScopeResolver`

| Method | Contract |
|---|---|
| `scope(Request $request): IdempotencyScope` | Whose keys these are: two callers never share a scope, so one cannot replay another's answer. Two tenants, two clients or two users are two callers. It may throw a `ProblemException` to refuse a caller. The endpoint's `#[Idempotent(anonymous: …)]`, when it gives one, is the `AnonymousCallers` case in the request attribute `IdempotencyListener::ANONYMOUS_CALLERS` |

## Attributes

| Attribute | What it extends |
|---|---|
| `#[Idempotent]` | A controller that requires an `Idempotency-Key`; its `ttl` replaces `idempotency.ttl`, and its `anonymous` replaces `idempotency.anonymous` — see [Idempotency](idempotency.md) |
| `#[DeprecatedEndpoint]` | A deprecated controller, its dates and link, checked when the container is built — see [Deprecations](deprecations.md) |

## Rules of verification

A service implementing `Phil\ApiContract\Contract\OpenApiRule` of `phil/api-contract`, tagged
`phil_api_contract.openapi_rule` by autoconfiguration or by hand, joins the verifier after the built-in rules — see
[Verification](verification.md#the-applications-own-rules).

## Problems

`ProblemException` carries any `ProblemDetails` the application builds — its type, extensions and headers — and is
answered as a problem wherever it is thrown; `problem_details.paths` widens that to every exception under the paths
given. `ProblemDetailsResponse` writes one from a controller. See [Problems](problems.md).

## Configuration

| Option | What it extends |
|---|---|
| `problem_details.paths` | Where every exception is answered as a problem |
| `idempotency.ttl`, `reservation_ttl` | How long a key is remembered, and reserved |
| `idempotency.replayed_headers` | The headers replayed with a body |
| `idempotency.anonymous` | Anonymous callers refused, or scoped by address; `#[Idempotent(anonymous: …)]` decides for one endpoint |
| `idempotency.messenger.tenant_scope`, `idempotency.messenger.buses` | The keys of messages scoped by their tenant, through `TenantIdempotencyScopeMiddleware`, which goes on every bus by its `#[AsMessageMiddleware]`, or on those named |
| `idempotency.client_attribute` | The token attribute that names the API client |
| `idempotency.connection` | Where the keys are kept; `null` for a store of the application's own |
| `idempotency.cache_pool`, `lock_factory` | The keys in a cache pool instead, each reservation under a lock |
| `verification.document`, `level`, `fail_on` | What `phil:api-contract:verify` verifies, and how strictly, when its arguments leave it out |
| `verification.skip` | The rules the verifier leaves out |

## Services

`DeprecationObservingHttpClient` decorates `http_client`: other decorations of the client compose with it, and the
deprecations it observes reach `ObservedDeprecations` and `phil:api-contract:deprecations`.

`Phil\ApiContract\OpenApi\ContractVerifier` is public: a test of the application verifies with it a document it
generates.
