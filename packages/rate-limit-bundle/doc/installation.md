# Installation

## Requirements

- PHP 8.5 or later;
- Symfony 8.2 or later, with FrameworkBundle;
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

## Worker mode

Every service of the bundle keeps nothing between requests: the decision of a request travels in a request attribute,
the counts in the storage. A long-running worker serves every request with the same services.
