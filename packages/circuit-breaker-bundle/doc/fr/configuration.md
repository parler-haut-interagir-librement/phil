<!-- phil-dev source-sha256=1ebda262bda514e672fef1f88aa5d3f271170a9c489d791aff4666f85735e781 -->
# Configuration

## Toutes les clés

```yaml
phil_circuit_breaker:
    # Service id of the PSR-20 clock of the windows and open durations.
    clock: clock

    # Service id of a CircuitStateStore shared by every breaker; null (the default) keeps the states in cache_pool.
    state_store: ~

    # The PSR-6 pool of the default store, read when state_store is null; shared by every server.
    cache_pool: cache.app

    # true accepts a cache_pool local to the server in the prod environment, for an application on one server.
    single_server: false

    # One circuit breaker per key.
    circuits:
        http.payments:
            failure_rate_percent: 50     # required, 1 to 100
            minimum_calls: 10            # required, at least 1
            window_seconds: 60           # required, at least 1
            open_seconds: 30             # required, at least 1
            half_open_successes: 1       # at least 1
            slow_call_milliseconds: 2000 # a call that lasts longer is slow; null (the default) counts none
            slow_call_rate_percent: 50   # 1 to 100, 100 by default
            http_client: payments.client # the client to put behind the circuit, or null
```

## `clock`

| | |
|---|---|
| Type | chaîne (identifiant de service) |
| Valeur par défaut | `clock` |
| Effet | L'horloge PSR-20 que lit chaque disjoncteur. |

## `state_store`

| | |
|---|---|
| Type | chaîne (identifiant de service) ou `null` |
| Valeur par défaut | `null` |
| Effet | Le `CircuitStateStore` partagé par chaque disjoncteur ; `null` garde les états dans `cache_pool` par l'intermédiaire de `CacheCircuitStateStore`. |

## `cache_pool`

| | |
|---|---|
| Type | chaîne (identifiant de service) |
| Valeur par défaut | `cache.app` |
| Effet | Le pool PSR-6 de `CacheCircuitStateStore`, quand `state_store` vaut `null`. En production, un pool qui reste sur chaque serveur — l'adaptateur de fichiers, APCu, tableau ou système de `symfony/cache`, ce que `cache.app` est par défaut — est refusé, sauf si `single_server` vaut `true`. |

## `single_server`

| | |
|---|---|
| Type | booléen |
| Valeur par défaut | `false` |
| Effet | Accepte en production un `cache_pool` local au serveur : sur un seul serveur, chaque *worker* le partage. Un `state_store` propre à l'application n'est jamais vérifié. |

## `circuits`

Une table de clés de circuit vers leur politique, lue par `ConfiguredCircuitBreakers`. Les clés ne sont pas normalisées.

| Champ | Type | Valeur par défaut | Sens |
|---|---|---|---|
| *(clé)* | table | — | Des segments ASCII en minuscules séparés par `.`, `_` ou `-`, de 87 octets au plus. |
| `failure_rate_percent` | entier | obligatoire | La part d'appels échoués qui ouvre le circuit, de 1 à 100. |
| `minimum_calls` | entier | obligatoire | Les appels qu'une fenêtre doit compter avant de pouvoir ouvrir le circuit. |
| `window_seconds` | entier | obligatoire | La longueur de la fenêtre fixe. |
| `open_seconds` | entier | obligatoire | Combien de temps le circuit reste ouvert avant les appels d'essai. |
| `half_open_successes` | entier | `1` | Les succès d'essai consécutifs qui le ferment. |
| `slow_call_milliseconds` | entier ou `null` | `null` | La durée que dépasse un appel lent, au moins 1 ; `null` ne compte aucun appel comme lent. |
| `slow_call_rate_percent` | entier | `100` | La part d'appels lents qui ouvre le circuit, de 1 à 100, lue quand il y a un seuil. |
| `http_client` | chaîne (identifiant de service) ou `null` | `null` | Le client HTTP que le bundle décore avec `CircuitBreakingHttpClient` : voir [Clients HTTP](http-clients.md). |

Avec au moins un circuit, le bundle enregistre `CircuitBreakers`, construit par
`ConfiguredCircuitBreakers::fromConfiguration()` sur les politiques, le stockage, `CircuitOverrides` et l'horloge ; le
stockage par défaut quand `state_store` vaut `null` ; un client décoré par `http_client` ; et le collecteur du
profileur. Sans circuit, il n'enregistre aucun d'eux, et le paramètre des clés est une liste vide.

## Erreurs

Chaque circuit est lu pendant la construction du conteneur : un circuit mal formé fait donc échouer `cache:clear` plutôt
que le premier appel à son tiers. Le message nomme le circuit :

| Message | Cause |
|---|---|
| `phil_circuit_breaker.circuits.<key>: "<key>" is not a circuit key: …` | Une clé hors de l'alphabet. |
| `phil_circuit_breaker.circuits.<key>: A circuit key is at most 87 characters; …` | Une clé trop longue. |
| `phil_circuit_breaker.circuits.<key>: A failure rate threshold is between 1 and 100 %; got 0.` | Un taux hors des bornes. |
| `phil_circuit_breaker.circuits.<key>: The window of a circuit policy is at least 1; got 0.` | Une durée ou un nombre inférieur à 1. |
| `phil_circuit_breaker.state_store must be a service id.` | Un identifiant de service qui n'est pas une chaîne. |
| `phil_circuit_breaker.cache_pool: the pool "<id>" is local to each server, …` | En production, un pool sur le système de fichiers, APCu, un tableau ou l'adaptateur système, sans `single_server`. |

Chacune est une `InvalidConfigurationException` ; celles qui concernent un circuit ont l'`InvalidCircuit` de la
bibliothèque pour exception précédente. Un champ obligatoire manquant ou une valeur qui n'est pas un entier est d'abord refusé par l'arbre de configuration.
