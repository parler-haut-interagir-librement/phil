<!-- phil-dev source-sha256=db2d09acb6486e4ba959ea1f598ed66a5002983240991be32500794dbc12e9b2 -->
# Référence

## Classes

| Classe | Rôle |
|---|---|
| `Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle` | Le bundle, sa configuration et ses services ; `OVERRIDE_TAG`, `CIRCUIT_KEYS`, `HTTP_CLIENT_PREFIX` |
| `Phil\CircuitBreakerBundle\Infrastructure\Configuration\ConfiguredCircuitBreakers` | `CircuitBreakers` issu de la configuration ; `validate()` au chargement |
| `Phil\CircuitBreakerBundle\Infrastructure\Cache\CacheCircuitStateStore` | Les états dans un pool PSR-6, sous `PREFIX` |
| `Phil\CircuitBreakerBundle\Infrastructure\HttpClient\CircuitBreakingHttpClient` | Un client HTTP derrière son circuit |
| `Phil\CircuitBreakerBundle\Infrastructure\HttpClient\Exception\CircuitOpen` | Une requête non envoyée parce que son circuit est ouvert ; une exception de transport |
| `Phil\CircuitBreakerBundle\Infrastructure\Profiler\CircuitBreakerDataCollector` | L'état de chaque circuit dans le profileur : `circuits()`, `trippedCount()`, `TEMPLATE` |
| `Phil\CircuitBreakerBundle\Infrastructure\Profiler\CircuitsReport` | Ce qu'une requête a collecté |
| `Phil\CircuitBreakerBundle\Infrastructure\DependencyInjection\SharedStateGuard` | La passe de compilation qui refuse, en production, un `cache_pool` local à chaque serveur ; `POOL_PARAMETER` |

## Services, étiquette et paramètre

| Identifiant | Enregistré quand | Ce que c'est |
|---|---|---|
| `Phil\CircuitBreaker\CircuitBreakers` | un circuit est déclaré | Les disjoncteurs par clé, à injecter là où un appel est protégé |
| `Phil\CircuitBreaker\CircuitOverrides` | un circuit est déclaré | Les forçages étiquetés, interrogés ensemble |
| `Phil\CircuitBreakerBundle\Infrastructure\Cache\CacheCircuitStateStore` | un circuit est déclaré et `state_store` vaut `null` | Le stockage par défaut |
| `phil_circuit_breaker.http_client.<key>` | un circuit a `http_client` | Le décorateur de ce client |
| `Phil\CircuitBreakerBundle\Infrastructure\Profiler\CircuitBreakerDataCollector` | un circuit est déclaré | Le collecteur, étiqueté `data_collector` avec l'identifiant `phil_circuit_breaker` |
| étiquette `phil_circuit_breaker.override` | toujours | Les forçages ; donnée par l'autoconfiguration à chaque `CircuitOverride` |
| paramètre `phil_circuit_breaker.circuit_keys` | toujours | Les clés des circuits déclarés, une liste de chaînes |
| paramètre `.phil_circuit_breaker.guarded_pool` | un circuit est déclaré, `state_store` vaut `null` et `single_server` vaut `false` | Le pool que `SharedStateGuard` vérifie, retiré par lui |

## Gabarit

`@PhilCircuitBreaker/profiler/collector.html.twig` : l'élément de la barre d'outils — les circuits non fermés sur le
total, chacun avec son état — et le panneau, un tableau des circuits et de leurs états.
