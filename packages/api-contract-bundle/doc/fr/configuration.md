<!-- phil-dev source-sha256=fe7061d6dd1931011a551f79b170af380dfbebd425a7d904ba132cb00e58c0ba -->
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
        # 403 problem; "ip" scopes it by its address, shared by every caller behind that address. "refuse" by default.
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
que le bundle ne connaît pas, un `idempotency.anonymous` autre que `refuse` ou `ip`, et un `verification.level` ou un
`verification.fail_on` hors de ses valeurs ; `#[Idempotent(ttl: 0)]`, ou un `ttl` négatif, l'est aussi à la lecture de
l'attribut. Un identifiant de `verification.skip` qu'aucune règle ne porte fait échouer la construction du vérificateur.

## Les services

| Service | Nature |
|---|---|
| `Phil\Idempotency\Contract\IdempotencyStore` | Public ; un alias du `DbalIdempotencyStore` de `phil/idempotency` avec une connexion, ou de son `CacheIdempotencyStore` avec un pool de cache |
| `Phil\ApiContract\Deprecation\ObservedDeprecations` | Public ; un alias de `CacheObservedDeprecations`, sur `cache.app` |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver` | Un alias de `CallerScopeResolver`, sur les deux ports ci-dessous, le jeton de sécurité et `idempotency.anonymous` |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver` | Un alias de `ActiveTenantResolver`, sur le `TenantContext` de `phil/tenancy-bundle` quand il est installé |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyClientResolver` | Un alias de `TokenAttributeClientResolver`, sur le jeton de sécurité et `idempotency.client_attribute` |
| `IdempotencyListener` | Sur `kernel.controller_arguments` à -10100, après les écouteurs des attributs du contrôleur, et sur `kernel.response` |
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
    App\Tenancy\TenantResolver: ~
    Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver: '@App\Tenancy\TenantResolver'
```

Il en va de même pour `IdempotencyClientResolver`, `IdempotencyScopeResolver`, `IdempotencyStore` et
`ObservedDeprecations`.
