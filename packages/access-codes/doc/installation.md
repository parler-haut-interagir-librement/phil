# Installation

```sh
composer require phil/access-codes
```

The package needs PHP 8.5 or later with `ext-mbstring`, `phil/url`, `psr/clock` and `symfony/rate-limiter` 8.2 or
later. The database store needs `doctrine/dbal` 4.5 or later:

```sh
composer require doctrine/dbal
```

## The key

The codes are stored as their HMAC-SHA-256 under a key of at least 32 bytes. Generate it once and keep it in the
secrets vault, never in the repository:

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
php bin/console secrets:set ACCESS_CODES_KEY
```

A key lost is every stored code lost: no code of an existing batch can be found without it. See
[Keys and storage](storage.md) for its rotation.

## The services in Symfony

The library declares no service. In `config/services.yaml`, with the DBAL store and a rate limiter declared in
`framework.rate_limiter`:

```yaml
framework:
    rate_limiter:
        access_code_redemption:
            policy: sliding_window
            limit: 10
            interval: '15 minutes'
            cache_pool: cache.redis

services:
    Phil\AccessCodes\Dbal\DbalCodeStore:
        arguments: ['@doctrine.dbal.default_connection']

    Phil\AccessCodes\CodeHasher:
        arguments: ['%env(base64:ACCESS_CODES_KEY)%']

    Phil\AccessCodes\AccessCodes:
        arguments:
            $store: '@Phil\AccessCodes\Dbal\DbalCodeStore'
            $attempts: '@limiter.access_code_redemption'
```

The clock is Symfony's `clock` service, autowired by its `Psr\Clock\ClockInterface`. Count the attempts in a store
shared by every server, Redis or a database, so that a client behind a load balancer cannot spend the limit once per
server.

Then create the tables, from a migration that calls `DbalCodeStore::configureSchema()`; see
[Keys and storage](storage.md).
