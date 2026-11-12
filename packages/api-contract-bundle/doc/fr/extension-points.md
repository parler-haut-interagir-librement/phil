<!-- phil-dev source-sha256=a7e1bee634a8677ac6082246aa563ad0a3bdbbcaf6964028ead513fa897a21e1 -->
# Points d'extension

Ce qu'une application implémente, remplace, configure ou utilise. Le port `IdempotencyStore` est celui de
`phil/idempotency`, et `ObservedDeprecations` celui de `phil/api-contract`, avec leurs contrats et leurs invariants.

## Ports

Chaque port est un alias que l'application fait pointer vers un service qui lui est propre — voir
[Configuration](configuration.md#remplacer-un-port).

| Port | Fourni | Ce que fait celui de l'application |
|---|---|---|
| `Phil\Idempotency\Contract\IdempotencyStore` | `DbalIdempotencyStore` sur `idempotency.connection`, ou `CacheIdempotencyStore` sur `idempotency.cache_pool` | Conserve les clés ailleurs selon les invariants de la bibliothèque |
| `Phil\ApiContract\Deprecation\ObservedDeprecations` | `CacheObservedDeprecations`, sur `cache.app` | Conserve ailleurs les obsolescences qu'observent les clients de l'application |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver` | `CallerScopeResolver` : l'identifiant de l'utilisateur, ou l'adresse du client | Nomme l'appelant autrement : l'identifiant propre d'un client d'API |

### `IdempotencyScopeResolver`

| Méthode | Contrat |
|---|---|
| `scope(Request $request): IdempotencyScope` | À qui appartiennent ces clés : deux appelants ne partagent jamais une portée, et l'un ne peut donc pas rejouer la réponse d'un autre |

## Attributs

| Attribut | Ce qu'il étend |
|---|---|
| `#[Idempotent]` | Un contrôleur qui exige un `Idempotency-Key` ; son `ttl` remplace `idempotency.ttl` — voir [Idempotence](idempotency.md) |
| `#[DeprecatedEndpoint]` | Un contrôleur obsolète, ses dates et son lien, vérifiés à la construction du conteneur — voir [Obsolescences](deprecations.md) |

## Règles de vérification

Un service qui implémente `Phil\ApiContract\Contract\OpenApiRule` de `phil/api-contract`, étiqueté
`phil_api_contract.openapi_rule` par autoconfiguration ou à la main, rejoint le vérificateur après les règles
intégrées — voyez [Vérification](verification.md#les-règles-propres-à-lapplication).

## Problèmes

`ProblemException` porte tout `ProblemDetails` que construit l'application — son type, ses extensions et ses en-têtes —
et reçoit une réponse sous forme de problème, où qu'elle soit levée. `problem_details.paths` étend ce comportement à
toute exception sous les chemins donnés. `ProblemDetailsResponse` en écrit un depuis un contrôleur. Voir
[Problèmes](problems.md).

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `problem_details.paths` | Où chaque exception reçoit une réponse sous forme de problème |
| `idempotency.ttl`, `reservation_ttl` | Combien de temps une clé est retenue, et réservée |
| `idempotency.replayed_headers` | Les en-têtes rejoués avec un corps |
| `idempotency.connection` | Où les clés sont conservées ; `null` pour un stockage propre à l'application |
| `idempotency.cache_pool`, `lock_factory` | Les clés plutôt dans un pool de cache, chaque réservation sous un verrou |
| `verification.document`, `level`, `fail_on` | Ce que vérifie `phil:api-contract:verify`, et avec quelle rigueur, quand ses arguments l'omettent |
| `verification.skip` | Les règles que le vérificateur écarte |

## Services

`DeprecationObservingHttpClient` décore `http_client` : les autres décorations du client se composent avec lui, et les
obsolescences qu'il observe atteignent `ObservedDeprecations` et `phil:api-contract:deprecations`.

`Phil\ApiContract\OpenApi\ContractVerifier` est public : un test de l'application vérifie avec lui un document qu'elle
génère.
