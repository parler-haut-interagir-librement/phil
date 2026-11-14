<!-- phil-dev source-sha256=c0c3ccdda982a916751b4374660619cf7a1bb5e79ce1535ae0bb6a66f5136695 -->
# Installation

## Prérequis

| Paquet | Version | Utilisé pour |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `phil/migration` | ^0.1 | Les annonces, les canaux, les décorateurs |
| `doctrine/doctrine-migrations-bundle` | ^4.0 | Les migrations, et la fabrique de dépendances qu'enveloppe le bundle |
| `doctrine/migrations` | ^3.9 | La fabrique de migrations et le migrateur |
| `symfony/notifier` | ^8.2 | Le chatter par lequel les canaux sont envoyés |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La configuration et les services |

## Enregistrer

```php
// config/bundles.php
return [
    // ...
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Phil\MigrationBundle\PhilMigrationBundle::class => ['all' => true],
];
```

## Les transports de chatter

Chaque canal nomme un transport de chatter du notifier :

```yaml
# config/packages/notifier.yaml
framework:
    notifier:
        chatter_transports:
            discord_releases: '%env(DISCORD_RELEASES_DSN)%'
            discord_news: '%env(DISCORD_NEWS_DSN)%'
```

Sans aucun canal, les migrations annoncent quand même (`note()` et `news()` fonctionnent toujours) et rien n'est envoyé,
de sorte que le chatter n'est pas nécessaire.
