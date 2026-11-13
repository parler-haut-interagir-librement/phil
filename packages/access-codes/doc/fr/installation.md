<!-- phil-dev source-sha256=a06c7407993f8771438677dee625d493f9653185c58abbb8e5b1561e2d4e17e1 -->
# Installation

```sh
composer require phil/access-codes
```

Le paquet exige PHP 8.5 ou une version plus récente avec `ext-mbstring`, `phil/url`, `psr/clock` et
`symfony/rate-limiter` 8.2 ou une version plus récente. Le magasin en base de données exige `doctrine/dbal` 4.5 ou une
version plus récente :

```sh
composer require doctrine/dbal
```

## La clé

Les codes sont stockés sous la forme de leur HMAC-SHA-256 calculé avec une clé d'au moins 32 octets. Générez-la une
seule fois et gardez-la dans le coffre de secrets, jamais dans le dépôt :

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
php bin/console secrets:set ACCESS_CODES_KEY
```

Une clé perdue, c'est tous les codes stockés perdus : sans elle, aucun code d'un lot existant ne peut être retrouvé.
Voir [Clés et stockage](storage.md) pour sa rotation.

## Les services dans Symfony

La bibliothèque ne déclare aucun service. Dans `config/services.yaml`, avec le magasin DBAL et un limiteur de débit
déclaré dans `framework.rate_limiter` :

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

L'horloge est le service `clock` de Symfony, câblé automatiquement par son interface `Psr\Clock\ClockInterface`.
Comptez les tentatives dans un magasin partagé par tous les serveurs, Redis ou une base de données, pour qu'un client
derrière un répartiteur de charge ne puisse pas dépenser la limite une fois par serveur.

Créez ensuite les tables, depuis une migration qui appelle `DbalCodeStore::configureSchema()` ; voir
[Clés et stockage](storage.md).
