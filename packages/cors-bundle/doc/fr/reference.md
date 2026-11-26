<!-- phil-dev source-sha256=ddd74233d9a6e45cc889ba0aff82173948d6eed4ef85187273839e8fd9f1754b -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\CorsBundle\PhilCorsBundle` | Le bundle : sa configuration `phil_cors`, ses services, et `REQUEST_PRIORITY`, 250, la priorité de l'écouteur de préambules. |
| `Phil\CorsBundle\Domain\CorsPolicy` | Ce qu'autorise un ensemble de chemins : origines, motifs, méthodes, en-têtes, en-têtes exposés, identifiants de connexion, `max_age`, réseau privé. Vérifie ses valeurs à la construction ; `fromConfig()` en construit une à partir des options d'un chemin. |
| `Phil\CorsBundle\Domain\PathRule` | Un motif sur le chemin et sa politique ; `matches()` teste un chemin, `compile()` vérifie un motif et y ajoute ses délimiteurs. |
| `Phil\CorsBundle\Application\PolicyResolver` | L'interface qui trouve la politique d'une requête, ou aucune. |
| `Phil\CorsBundle\Infrastructure\PathPolicyResolver` | Le résolveur de la configuration : la première règle qui correspond au chemin. |
| `Phil\CorsBundle\Application\CorsHeaders` | `isPreflight()`, `preflight()` construit la réponse à un préambule, `apply()` pose les en-têtes d'une autre réponse ; les bornes `REQUEST_HEADERS_MAX_BYTES` et `REQUEST_HEADERS_MAX_COUNT`. |
| `Phil\CorsBundle\Infrastructure\EventListener\CorsListener` | Répond aux préambules sur `kernel.request` et décore les réponses sur `kernel.response`. |
| `Phil\CorsBundle\Domain\Exception\InvalidCorsPolicy` | Une valeur refusée à la construction d'une politique ou d'une règle. |

## `CorsPolicy`

| Membre | Rôle |
|---|---|
| `__construct(allowOrigins, allowOriginPatterns, allowMethods, allowHeaders, exposeHeaders, allowCredentials, maxAge, allowPrivateNetwork)` | Construit et vérifie une politique ; les valeurs par défaut sont celles de la configuration. |
| `allowsOrigin(string $origin): bool` | Si l'origine est autorisée, comparée telle qu'envoyée ; faux au-delà de `ORIGIN_MAX_BYTES`, 512. |
| `allowOriginValue(string $origin): string` | `*` pour toute origine, l'origine sinon. |
| `allowsMethod(string $method): bool` | Si la méthode est autorisée, sans tenir compte de la casse. |
| `allowsHeaders(list<string> $headers): bool` | Si chaque nom en minuscules est autorisé. |
| `$methods`, `$allowHeaders`, `$anyHeader`, `$exposeHeaders`, `$allowCredentials`, `$maxAge`, `$allowPrivateNetwork` | Les valeurs, lues par `CorsHeaders`. |

## Services

| Service | Classe | Rôle |
|---|---|---|
| `phil_cors.path_policy_resolver` | `PathPolicyResolver` | Les règles de `phil_cors.paths`. |
| `Phil\CorsBundle\Application\PolicyResolver` | alias | Le résolveur qu'emploie l'écouteur ; `phil_cors.path_policy_resolver` par défaut. |
| `phil_cors.headers` | `CorsHeaders` | Les en-têtes d'une politique. |
| `phil_cors.listener` | `CorsListener` | L'écouteur de `kernel.request` (priorité 250) et de `kernel.response` (priorité 0). |

Avec `enabled: false`, aucun n'est enregistré.
