# Installation

## Requirements

| Package | Version | Used for |
|---|---|---|
| PHP | 8.5 or later | |
| `phil/migration` | ^0.1 | The announcements, the channels, the decorators |
| `doctrine/doctrine-migrations-bundle` | ^3.7 | The migrations, and the dependency factory the bundle wraps |
| `doctrine/migrations` | ^3.9 | The migration factory and the migrator |
| `symfony/notifier` | ^8.2 | The chatter the channels are sent through |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The configuration and the services |

## Registering

```php
// config/bundles.php
return [
    // ...
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Phil\MigrationBundle\PhilMigrationBundle::class => ['all' => true],
];
```

## The chatter transports

Each channel names a chatter transport of the notifier:

```yaml
# config/packages/notifier.yaml
framework:
    notifier:
        chatter_transports:
            discord_releases: '%env(DISCORD_RELEASES_DSN)%'
            discord_news: '%env(DISCORD_NEWS_DSN)%'
```

Without any channel, the migrations still announce — `note()` and `news()` keep working — and nothing is sent, so
the chatter is not needed.
