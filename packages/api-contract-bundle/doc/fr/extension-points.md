<!-- phil-dev source-sha256=f54fc16eeb4475c7b93ea9b8afaa94254aaa2f8b9ddfe5f74d93f59df7e16c26 -->
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
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver` | `ActiveTenantResolver` : le locataire actif de `phil/tenancy-bundle`, ou aucun | Retourne le locataire que l'application a vérifié, par ses propres moyens |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyClientResolver` | `TokenAttributeClientResolver` : l'attribut du jeton `idempotency.client_attribute` | Retourne le client d'API ou OAuth que l'application a authentifié |
| `Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver` | `CallerScopeResolver` : le locataire, le client et l'utilisateur ; les appelants anonymes refusés ou rattachés à leur adresse | Nomme l'appelant tout autrement |

### `IdempotencyTenantResolver`

| Méthode | Contrat |
|---|---|
| `tenant(Request $request): ?string` | La référence stable du locataire dans lequel la requête agit, une fois vérifié ; jamais un hôte ni un en-tête que le client a choisi. Nulle hors de tout locataire |

### `IdempotencyClientResolver`

| Méthode | Contrat |
|---|---|
| `client(Request $request): ?string` | Le client authentifié qui a envoyé la requête ; nulle quand elle n'en nomme aucun |

### `IdempotencyScopeResolver`

| Méthode | Contrat |
|---|---|
| `scope(Request $request): IdempotencyScope` | À qui appartiennent ces clés : deux appelants ne partagent jamais une portée, et l'un ne peut donc pas rejouer la réponse d'un autre. Deux locataires, deux clients ou deux utilisateurs sont deux appelants. Elle peut lever une `ProblemException` pour refuser un appelant. Le `#[Idempotent(anonymous: …)]` du point de terminaison, quand il en donne un, est le cas `AnonymousCallers` de l'attribut de requête `IdempotencyListener::ANONYMOUS_CALLERS` |

## Attributs

| Attribut | Ce qu'il étend |
|---|---|
| `#[Idempotent]` | Un contrôleur qui exige un `Idempotency-Key` ; son `ttl` remplace `idempotency.ttl`, et son `anonymous` remplace `idempotency.anonymous` — voir [Idempotence](idempotency.md) |
| `#[DeprecatedEndpoint]` | Un contrôleur obsolète, ses dates et son lien, vérifiés à la construction du conteneur — voir [Obsolescences](deprecations.md) |

## Règles de vérification

Un service qui implémente `Phil\ApiContract\Contract\OpenApiRule` de `phil/api-contract`, étiqueté
`phil_api_contract.openapi_rule` par autoconfiguration ou à la main, rejoint le vérificateur après les règles
intégrées — voyez [Vérification](verification.md#les-règles-propres-à-lapplication).

## Problèmes

`ProblemException` porte tout `ProblemDetails` que construit l'application — son type, ses extensions et ses en-têtes —
et reçoit une réponse sous forme de problème, où qu'elle soit levée. `problem_details.paths` étend ce comportement à
toute exception sous les chemins donnés. `ProblemDetailsResponse` en écrit un depuis un contrôleur. Une requête que
traite API Platform garde ses propres erreurs sous les chemins, et ses contrôleurs ignorent `#[Idempotent]` et
`#[DeprecatedEndpoint]`. Voir [Problèmes](problems.md#les-requêtes-que-traite-api-platform).

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `problem_details.paths` | Où chaque exception reçoit une réponse sous forme de problème |
| `idempotency.ttl`, `reservation_ttl` | Combien de temps une clé est retenue, et réservée |
| `idempotency.replayed_headers` | Les en-têtes rejoués avec un corps |
| `idempotency.anonymous` | Les appelants anonymes refusés, ou rattachés à leur adresse ; `#[Idempotent(anonymous: …)]` en décide pour un point de terminaison |
| `idempotency.messenger.tenant_scope`, `idempotency.messenger.buses` | Les clés des messages rattachées à leur locataire, par `TenantIdempotencyScopeMiddleware`, qui va sur chaque bus par son `#[AsMessageMiddleware]` ou sur ceux qui sont nommés |
| `idempotency.client_attribute` | L'attribut du jeton qui nomme le client d'API |
| `idempotency.connection` | Où les clés sont conservées ; `null` pour un stockage propre à l'application |
| `idempotency.cache_pool`, `lock_factory` | Les clés plutôt dans un pool de cache, chaque réservation sous un verrou |
| `verification.document`, `level`, `fail_on` | Ce que vérifie `phil:api-contract:verify`, et avec quelle rigueur, quand ses arguments l'omettent |
| `verification.skip` | Les règles que le vérificateur écarte |

## Services

`DeprecationObservingHttpClient` décore `http_client` : les autres décorations du client se composent avec lui, et les
obsolescences qu'il observe atteignent `ObservedDeprecations` et `phil:api-contract:deprecations`.

`Phil\ApiContract\OpenApi\ContractVerifier` est public : un test de l'application vérifie avec lui un document qu'elle
génère.
