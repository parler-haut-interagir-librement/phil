# Configuration

```yaml
# config/packages/phil_api_contract.yaml
phil_api_contract:
    problem_details:
        # Regular expressions on the path under which every exception is answered as a problem. None by default:
        # a ProblemException is answered as a problem wherever it is thrown.
        paths: ['^/api/']
    idempotency:
        # How many seconds an answered key is remembered; a controller's #[Idempotent(ttl: …)] replaces it. 86400
        # by default.
        ttl: 86400
        # How many seconds a key stays reserved while its first request runs: the longest a retry is answered 409
        # after a process died before its response. Longer than the slowest request. 300 by default.
        reservation_ttl: 300
        # What an #[Idempotent] endpoint does with a caller that is neither a user nor a client: "refuse" answers a
        # 403 problem; "ip" scopes it by its address, shared by every caller behind that address. "refuse" by default;
        # a controller's #[Idempotent(anonymous: …)] replaces it.
        anonymous: refuse
        # The attribute of the security token that names the API client, part of the scope of a key; null reads
        # none. "client_id" by default.
        client_attribute: client_id
        # The response headers kept and replayed with the body.
        replayed_headers: [Content-Type, Location]
        # The Doctrine connection that keeps the keys; null keeps them elsewhere. "default" when left out, null
        # without DoctrineBundle.
        connection: default
        # A cache pool that keeps the keys instead of the connection, shared by every server. Null by default.
        cache_pool: ~
        # The lock factory that makes a reservation in the cache pool atomic, on a store shared by every server.
        lock_factory: lock.factory
        messenger:
            # Adds TenantIdempotencyScopeMiddleware, which puts the tenant of a received message into the scope of
            # its IdempotencyStamp, to the buses, before the idempotency middleware. Needs PhilTenancyBundle.
            # False by default.
            tenant_scope: false
            # The buses the middleware is added to. None by default: every bus whose middleware holds the
            # IdempotencyMiddleware of phil/idempotency.
            buses: []
    verification:
        # The OpenAPI document phil:api-contract:verify reads when none is named. Null by default.
        document: '%kernel.project_dir%/openapi.yaml'
        # The rules run when no --level is given: minimal, recommended or strict. "recommended" by default.
        level: recommended
        # The severity that fails the command when no --fail-on is given: error or warning. "error" by default.
        fail_on: error
        # The ids of the rules left out. None by default.
        skip: []
```

`idempotency.ttl: 0` and `idempotency.reservation_ttl: 0` are refused when the container is built, as is a key the
bundle does not know, an `idempotency.anonymous` other than `refuse` or `ip`, and a `verification.level` or
`verification.fail_on` outside its values, and `idempotency.messenger.tenant_scope: true` without `PhilTenancyBundle`;
so is `#[Idempotent(ttl: 0)]` or a negative one, or an `#[Idempotent(anonymous: …)]` other than `refuse` or `ip`, when
the attribute is read.
A `verification.skip` id that no rule has fails when the verifier is built.

## The services

| Service | What it is |
|---|---|
| `Phil\Idempotency\Contract\IdempotencyStore` | Public; an alias of the `DbalIdempotencyStore` of `phil/idempotency` with a connection, or of its `CacheIdempotencyStore` with a cache pool |
| `Phil\ApiContract\Deprecation\ObservedDeprecations` | Public; an alias of `CacheObservedDeprecations`, on `cache.app` |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver` | An alias of `CallerScopeResolver`, on the two ports below, the security token and `idempotency.anonymous` |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver` | An alias of `ActiveTenantResolver`, on the `TenantContext` of `phil/tenancy-bundle` when it is installed |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyClientResolver` | An alias of `TokenAttributeClientResolver`, on the security token and `idempotency.client_attribute` |
| `IdempotencyListener` | On `kernel.controller_arguments` at -10100, after the listeners of the controller's attributes, and on `kernel.response` |
| `TenantIdempotencyScopeMiddleware` | A Messenger middleware, when `idempotency.messenger.tenant_scope` is true: tagged `messenger.middleware` for the buses of `idempotency.messenger.buses`, before the idempotency middleware |
| `DeprecationObservingHttpClient` | Decorates `http_client`, when there is one |
| `Phil\ApiContract\OpenApi\ContractVerifier` | Public; built by `ContractVerifierFactory` from the library's rules, the services tagged `phil_api_contract.openapi_rule`, and `verification.skip` |
| `DeprecationsCommand`, `VerifyCommand` | `phil:api-contract:deprecations` and `phil:api-contract:verify`, when Symfony Console is installed |

Every service implementing `Phil\ApiContract\Contract\OpenApiRule` is tagged `phil_api_contract.openapi_rule` by
autoconfiguration.

The bundle also adds two compiler passes: `CheckDeprecatedEndpoints`, which builds every `#[DeprecatedEndpoint]` of
the services tagged `controller.service_arguments` and fails the build on one that cannot be sent; and
`AddTenantIdempotencyScopeMiddleware`, which adds the tenant scope of messages to the buses before Messenger reads
their middleware, and fails the build on a bus of `idempotency.messenger.buses` the application does not have.

## Replacing a port

Alias the port to a service of the application's own:

```yaml
# config/services.yaml
services:
    App\Tenancy\TenantResolver: ~
    Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver: '@App\Tenancy\TenantResolver'
```

The same holds for `IdempotencyClientResolver`, `IdempotencyScopeResolver`, `IdempotencyStore` and
`ObservedDeprecations`.
