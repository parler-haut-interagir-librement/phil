# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilApiContractBundle` | `final class`, `AbstractBundle` | Alias `phil_api_contract`; the configuration and the services; constant `RULE_TAG` |
| `Application\Library` | `final class` | The ports of the libraries the bundle aliases, by name |
| `Infrastructure\Http\ProblemException` | exception | A problem to answer with, and its headers |
| `Infrastructure\Http\ProblemDetailsResponse` | response | A problem, `application/problem+json` |
| `Infrastructure\Http\ProblemDetailsListener` | `kernel.exception` listener | Exceptions as problems |
| `Infrastructure\Http\Idempotent` | attribute | A controller that requires an `Idempotency-Key`; its retention, `ttl`, and its policy for anonymous callers, `anonymous` |
| `Infrastructure\Http\IdempotencyListener` | listener | The guard of `phil/idempotency` around such a controller, after its access checks; constants `REPLAYED_HEADER`, `RESERVED`, `ANONYMOUS_CALLERS`, `PRIORITY`, `RESERVATION_LOST`, `KEY_FORMAT` |
| `Infrastructure\Http\IdempotencyReservation` | value | The reservation a request took, from `phil/idempotency`, and the retention of its answer |
| `Infrastructure\Http\IdempotencyScopeResolver` | interface | Who sent a request |
| `Infrastructure\Http\CallerScopeResolver` | resolver | The tenant, the client and the user, hashed; anonymous callers refused or scoped by address, by the endpoint's policy or the configured one; constant `ANONYMOUS` |
| `Infrastructure\Messenger\TenantIdempotencyScopeMiddleware` | Messenger middleware | The tenant of a received message's `TenantStamp` in the scope of its `IdempotencyStamp`; constant `SCOPE_PREFIX` |
| `Infrastructure\Messenger\TenantScopedIdempotencyStamp` | Messenger stamp | The tenant an envelope's idempotency scope was scoped for, so that a retry is not scoped twice |
| `Infrastructure\Http\IdempotencyTenantResolver` | interface | The tenant a request acts in |
| `Infrastructure\Http\ActiveTenantResolver` | resolver | The active tenant of `phil/tenancy-bundle` |
| `Infrastructure\Http\IdempotencyClientResolver` | interface | The client that sent a request |
| `Infrastructure\Http\TokenAttributeClientResolver` | resolver | The client named by an attribute of the security token; constant `DEFAULT_ATTRIBUTE` |
| `Infrastructure\Http\AnonymousCallers` | string enum | `Refuse` (`refuse`), `ByIp` (`ip`) |
| `Infrastructure\Http\DeprecatedEndpoint` | attribute | A deprecated controller and its dates |
| `Infrastructure\Http\DeprecationListener` | listener | Its headers on each response, its `Link` beside the response's own |
| `Infrastructure\DependencyInjection\CheckDeprecatedEndpoints` | compiler pass | Every `#[DeprecatedEndpoint]` of the controller services, built when the container is |
| `Infrastructure\HttpClient\DeprecationObservingHttpClient` | HTTP client decorator | The signals of the APIs called |
| `Infrastructure\Deprecation\CacheObservedDeprecations` | `ObservedDeprecations` | The signals, an item per endpoint in a cache pool, kept a week past their last write |
| `Infrastructure\Doctrine\IdempotencySchemaListener` | Doctrine listener | The table `phil_idempotency` of `phil/idempotency` in the ORM's schema, built for the connection's platform |
| `Infrastructure\Console\DeprecationsCommand` | console command | `phil:api-contract:deprecations` |
| `Infrastructure\Console\VerifyCommand` | console command | `phil:api-contract:verify` |
| `Infrastructure\OpenApi\ContractVerifierFactory` | factory | The verifier from the library's rules, the tagged ones and `verification.skip` |
| `Infrastructure\OpenApi\DocumentReader` | reader | An OpenAPI document from a JSON or YAML file, bounded before it is read |
