<!-- phil-dev source-sha256=02066f1551d7aaf14fec6a9756784bf815c58b0f955a85a63ef84049b510bb8e -->
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

`idempotency.ttl: 0` et `idempotency.reservation_ttl: 0` sont refusés à la construction du conteneur, tout comme une clé
que le bundle ne connaît pas, et un `verification.level` ou un `verification.fail_on` hors de ses valeurs ;
`#[Idempotent(ttl: 0)]`, ou un `ttl` négatif, l'est aussi à la lecture de l'attribut. Un identifiant de
`verification.skip` qu'aucune règle ne porte fait échouer la construction du vérificateur.

## Les services

| Service | Nature |
|---|---|
| `Phil\Idempotency\Contract\IdempotencyStore` | Public ; un alias du `DbalIdempotencyStore` de `phil/idempotency` avec une connexion, ou de son `CacheIdempotencyStore` avec un pool de cache |
| `Phil\ApiContract\Deprecation\ObservedDeprecations` | Public ; un alias de `CacheObservedDeprecations`, sur `cache.app` |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver` | Un alias de `CallerScopeResolver` |
| `DeprecationObservingHttpClient` | Décore `http_client`, s'il existe |
| `Phil\ApiContract\OpenApi\ContractVerifier` | Public ; construit par `ContractVerifierFactory` à partir des règles de la bibliothèque, des services étiquetés `phil_api_contract.openapi_rule` et de `verification.skip` |
| `DeprecationsCommand`, `VerifyCommand` | `phil:api-contract:deprecations` et `phil:api-contract:verify`, quand Symfony Console est installé |

Chaque service qui implémente `Phil\ApiContract\Contract\OpenApiRule` reçoit l'étiquette
`phil_api_contract.openapi_rule` par autoconfiguration.

Le bundle ajoute aussi une passe de compilation, `CheckDeprecatedEndpoints`, qui construit chaque
`#[DeprecatedEndpoint]` des services étiquetés `controller.service_arguments` et fait échouer la construction sur un
attribut qui ne peut pas être envoyé.

## Remplacer un port

Faites pointer l'alias du port vers un service propre à l'application :

```yaml
# config/services.yaml
services:
    App\Api\ClientScopeResolver: ~
    Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver: '@App\Api\ClientScopeResolver'
```

Il en va de même pour `IdempotencyStore` et pour `ObservedDeprecations`.
