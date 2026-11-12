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
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver` | `CallerScopeResolver`: the user's identifier, or the client's address | Names the caller another way: an API client's own identifier |

### `IdempotencyScopeResolver`

| Method | Contract |
|---|---|
| `scope(Request $request): IdempotencyScope` | Whose keys these are: two callers never share a scope, so one cannot replay another's answer |

## Attributes

| Attribute | What it extends |
|---|---|
| `#[Idempotent]` | A controller that requires an `Idempotency-Key`; its `ttl` replaces `idempotency.ttl` — see [Idempotency](idempotency.md) |
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
| `idempotency.connection` | Where the keys are kept; `null` for a store of the application's own |
| `idempotency.cache_pool`, `lock_factory` | The keys in a cache pool instead, each reservation under a lock |
| `verification.document`, `level`, `fail_on` | What `phil:api-contract:verify` verifies, and how strictly, when its arguments leave it out |
| `verification.skip` | The rules the verifier leaves out |

## Services

`DeprecationObservingHttpClient` decorates `http_client`: other decorations of the client compose with it, and the
deprecations it observes reach `ObservedDeprecations` and `phil:api-contract:deprecations`.

`Phil\ApiContract\OpenApi\ContractVerifier` is public: a test of the application verifies with it a document it
generates.
