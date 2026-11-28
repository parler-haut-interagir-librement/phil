# Installation

## Requirements

- PHP 8.5 or later; the intl and mbstring extensions are suggested, for the speed of the normalization of the
  identifiers a key is built from, which the polyfills of `symfony/string` do the same without them;
- Symfony 8.2 or later, with FrameworkBundle and its `rate_limiter`, enabled by default, whose listener applies the
  `#[RateLimit]` attribute and the `RateLimit` argument;
- optionally TwigBundle for the refusal page, the form component for forms, SecurityBundle for the `user` and
  `user_or_ip` keys, the translator for French, symfony/lock for exact counts under concurrency,
  phil/ip-anonymizer for keys by network, and a PSR-3 logger such as MonologBundle for the warnings of a limiter in
  observation;
- phil/rate-limit, installed with the bundle, whose guard refuses in production a store local to each server.

## Installing

```sh
composer require phil/rate-limit-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\RateLimitBundle\PhilRateLimitBundle::class => ['all' => true],
];
```

## The storage

The limiters keep their state in a cache pool, `cache.app` by default. That pool lives on one server's disk: for an
application on several servers, or in workers that must share their counts, declare a shared pool and name it.

```yaml
framework:
    cache:
        pools:
            cache.rate_limits: { adapter: cache.adapter.redis, provider: '%env(REDIS_URL)%' }

phil_rate_limit:
    cache_pool: cache.rate_limits
```

A limiter names its own pool with `cache_pool`, or a `StorageInterface` service with `storage`. A pool, a storage or a
lock factory that is not defined fails the container build, with the limiter that needs it, rather than the first
request.

## The lock

A limiter reads its state, decides and writes it back. Without a lock, two requests of one key on two workers read the
same state, and both may pass a limit that has room for one of them. Each limiter therefore reserves under the lock
factory `lock.factory` when it is defined — `framework.lock` enabled, with symfony/lock —, as FrameworkBundle does for
its own limiters. Its store is shared by the servers, as the pool is:

```yaml
framework:
    lock: '%env(REDIS_URL)%'
```

`lock_factory` names another factory, for the bundle or for one limiter; a name that is not defined fails the build.
`lock_factory: null` reserves without a lock.

## Worker mode

Every service of the bundle keeps nothing between requests: the decision of a request travels in a request attribute,
the counts in the storage. A long-running worker serves every request with the same services.
