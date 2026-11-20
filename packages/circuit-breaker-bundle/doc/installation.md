# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | |
| `phil/circuit-breaker` | `^0.1` | The keys, policies, state machine and breakers. |
| `phil/rate-limit` | `^0.1` | `StoreLocality`, which tells a cache pool local to each server from a shared one. |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel`, `symfony/http-foundation` | `^8.2` | The configuration, the services and the profiler collector. |
| `psr/clock` | `^1.0` | The windows and open durations are measured on the application's clock. |
| `psr/cache` | `^3.0` | The default store of states, over a cache pool; not needed with a store of your own. |
| `symfony/http-client` | `^8.2` | The clients put behind their circuits; not needed without `http_client`. |
| `symfony/web-profiler-bundle` | `^8.2` | The profiler panel; nothing is collected without it. |

## Installing

```sh
composer require phil/circuit-breaker-bundle
```

Register the bundle, if Symfony Flex did not:

```php
// config/bundles.php
return [
    // ...
    Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle::class => ['all' => true],
];
```

## The cache pool

The default store keeps the states in `cache.app`. A circuit open in one worker is open in all only when they share
that pool: give the circuits a pool on a shared adapter, such as Redis. In the prod environment, the bundle refuses a
pool that stays on each server — the filesystem, APCu, array or system adapter, which `cache.app` is by default —:
each server would count its own failures, and keep calling a third party the others found down.

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

On one server, every worker shares a local pool; say so:

```yaml
phil_circuit_breaker:
    single_server: true
```

A pool that loses an entry closes that circuit, which is the safe direction: calls resume, and the circuit trips
again if the third party is still failing.

## Upgrading from 0.1

With circuits declared and no `state_store`, the prod container now refuses a `cache_pool` local to each server, and
`cache.app` on the filesystem is one. Name a pool on a shared adapter, as above, or set `single_server: true` on a
single server. The dev and test environments are not checked.
