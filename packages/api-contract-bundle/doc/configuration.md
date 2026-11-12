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
        # The response headers kept and replayed with the body.
        replayed_headers: [Content-Type, Location]
        # The Doctrine connection that keeps the keys; null keeps them elsewhere. "default" when left out, null
        # without DoctrineBundle.
        connection: default
        # A cache pool that keeps the keys instead of the connection, shared by every server. Null by default.
        cache_pool: ~
        # The lock factory that makes a reservation in the cache pool atomic, on a store shared by every server.
        lock_factory: lock.factory
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
bundle does not know, and a `verification.level` or `verification.fail_on` outside its values; so is
`#[Idempotent(ttl: 0)]` or a negative one, when the attribute is read. A `verification.skip` id that no rule has fails
when the verifier is built.

## The services

| Service | What it is |
|---|---|
| `Phil\Idempotency\Contract\IdempotencyStore` | Public; an alias of the `DbalIdempotencyStore` of `phil/idempotency` with a connection, or of its `CacheIdempotencyStore` with a cache pool |
| `Phil\ApiContract\Deprecation\ObservedDeprecations` | Public; an alias of `CacheObservedDeprecations`, on `cache.app` |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver` | An alias of `CallerScopeResolver` |
| `DeprecationObservingHttpClient` | Decorates `http_client`, when there is one |
| `Phil\ApiContract\OpenApi\ContractVerifier` | Public; built by `ContractVerifierFactory` from the library's rules, the services tagged `phil_api_contract.openapi_rule`, and `verification.skip` |
| `DeprecationsCommand`, `VerifyCommand` | `phil:api-contract:deprecations` and `phil:api-contract:verify`, when Symfony Console is installed |

Every service implementing `Phil\ApiContract\Contract\OpenApiRule` is tagged `phil_api_contract.openapi_rule` by
autoconfiguration.

The bundle also adds a compiler pass, `CheckDeprecatedEndpoints`, which builds every `#[DeprecatedEndpoint]` of the
services tagged `controller.service_arguments` and fails the build on one that cannot be sent.

## Replacing a port

Alias the port to a service of the application's own:

```yaml
# config/services.yaml
services:
    App\Api\ClientScopeResolver: ~
    Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver: '@App\Api\ClientScopeResolver'
```

The same holds for `IdempotencyStore` and `ObservedDeprecations`.
