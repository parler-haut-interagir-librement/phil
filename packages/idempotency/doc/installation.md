# Installation

```bash
composer require phil/idempotency
```

The library needs PHP 8.5 or later, `psr/clock` 1 and `symfony/uid` 8.2, which generates keys. The rest depends on
the parts you use:

| Part | Package |
|---|---|
| `Store\DbalIdempotencyStore`, `Store\IdempotencyTable` | `doctrine/dbal` 4.5 |
| `Store\CacheIdempotencyStore` | `psr/cache` 3 and `symfony/lock` 8.2 |
| `Messenger\IdempotencyMiddleware`, `Messenger\IdempotencyStamp` | `symfony/messenger` 8.2 |

The guard reads the time from a PSR-20 clock: `Symfony\Component\Clock\NativeClock`, or the `clock` service of a
Symfony application.

For the DBAL store, create the table in a migration, with the platform of the connection, so that MySQL and
MariaDB compare scopes and keys byte for byte:

```php
use Phil\Idempotency\Store\IdempotencyTable;

$schema = IdempotencyTable::configure($schema, $this->connection->getDatabasePlatform());
```

Next: [the rule](rule.md).
