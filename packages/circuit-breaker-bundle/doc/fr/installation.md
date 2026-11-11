<!-- phil-dev source-sha256=0fdfadcc784fd766dc004746a0be4f16f6862c11be5d4994f6d2259698570437 -->
# Installation

## Prérequis

| Paquet | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `phil/circuit-breaker` | `^0.1` | Les clés, les politiques, la machine à états et les disjoncteurs. |
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
s'ils partagent ce pool : sur plusieurs serveurs, donnez aux circuits un pool sur un adaptateur partagé, comme Redis.

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

Un pool qui perd une entrée ferme ce circuit, ce qui est la direction sûre : les appels reprennent, et le circuit se
déclenche de nouveau si le tiers échoue encore.
