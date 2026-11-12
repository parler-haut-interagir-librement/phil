<!-- phil-dev source-sha256=cc6b86646267f07971af760cadc63e4669d2c65635684e70d534e1e5452fad21 -->
# Installation

```bash
composer require phil/idempotency
```

La bibliothèque demande PHP 8.5 ou plus récent, `psr/clock` 1 et `symfony/uid` 8.2, qui génère les clés. Le reste
dépend des parties utilisées :

| Partie | Paquet |
|---|---|
| `Store\DbalIdempotencyStore`, `Store\IdempotencyTable` | `doctrine/dbal` 4.5 |
| `Store\CacheIdempotencyStore` | `psr/cache` 3 et `symfony/lock` 8.2 |
| `Messenger\IdempotencyMiddleware`, `Messenger\IdempotencyStamp` | `symfony/messenger` 8.2 |

La garde lit l'heure dans une horloge PSR-20 : `Symfony\Component\Clock\NativeClock`, ou le service `clock` d'une
application Symfony.

Pour le stockage DBAL, créez la table dans une migration, avec la plateforme de la connexion, pour que MySQL et
MariaDB comparent portées et clés octet par octet :

```php
use Phil\Idempotency\Store\IdempotencyTable;

$schema = IdempotencyTable::configure($schema, $this->connection->getDatabasePlatform());
```

Ensuite : [la règle](rule.md).
