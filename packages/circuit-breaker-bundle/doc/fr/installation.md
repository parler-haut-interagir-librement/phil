<!-- phil-dev source-sha256=6026185b5946576a813258721dafaf2dde5ff8e0bdf73687637c8dfc8171de5c -->
# Installation

## Prérequis

| Paquet | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `phil/circuit-breaker` | `^0.1` | Les clés, les politiques, la machine à états et les disjoncteurs. |
| `phil/rate-limit` | `^0.1` | `StoreLocality`, qui distingue un pool de cache local à chaque serveur d'un pool partagé. |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel`, `symfony/http-foundation` | `^8.2` | La configuration, les services et le collecteur du profileur. |
| `psr/clock` | `^1.0` | Les fenêtres et les durées d'ouverture sont mesurées sur l'horloge de l'application. |
| `psr/cache` | `^3.0` | Le stockage des états par défaut, sur un pool de cache ; inutile avec votre propre stockage. |
| `symfony/http-client` | `^8.2` | Les clients placés derrière leurs circuits ; inutile sans `http_client`. |
| `symfony/web-profiler-bundle` | `^8.2` | Le panneau du profileur ; rien n'est collecté sans lui. |

## Installer

```sh
composer require phil/circuit-breaker-bundle
```

Enregistrez le bundle, si Symfony Flex ne l'a pas fait :

```php
// config/bundles.php
return [
    // ...
    Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle::class => ['all' => true],
];
```

## Le pool de cache

Le stockage par défaut garde les états dans `cache.app`. Un circuit ouvert dans un *worker* n'est ouvert dans tous que
s'ils partagent ce pool : donnez aux circuits un pool sur un adaptateur partagé, comme Redis. En production, le bundle
refuse un pool qui reste sur chaque serveur — l'adaptateur de fichiers, APCu, tableau ou système, ce que `cache.app` est
par défaut — : chaque serveur compterait ses propres échecs et continuerait d'appeler un tiers que les autres ont
jugé en panne.

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            cache.circuits:
                adapter: cache.adapter.redis
```

```yaml
# config/packages/phil_circuit_breaker.yaml
phil_circuit_breaker:
    cache_pool: cache.circuits
```

Sur un seul serveur, chaque *worker* partage un pool local ; dites-le :

```yaml
phil_circuit_breaker:
    single_server: true
```

Un pool qui perd une entrée ferme ce circuit, ce qui est la direction sûre : les appels reprennent, et le circuit se
déclenche de nouveau si le tiers échoue encore.

## Mise à niveau depuis la 0.1

Avec des circuits déclarés et sans `state_store`, le conteneur de production refuse désormais un `cache_pool` local à
chaque serveur, et `cache.app` sur le système de fichiers en est un. Nommez un pool sur un adaptateur partagé, comme
ci-dessus, ou mettez `single_server: true` sur un serveur unique. Les environnements de développement et de test ne
sont pas vérifiés.
