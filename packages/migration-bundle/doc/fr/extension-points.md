<!-- phil-dev source-sha256=e8dcf18fde565ef8751db23bd52da99676e774d4ccebcfbcf080294ebfdd5734 -->
# Points d'extension

Ce qu'une application configure, remplace ou utilise. Les types sont ceux de `phil/migration`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `channels` | Où et comment chaque version est annoncée : un canal par transport et par locale, notes ou nouvelles, titres, message le plus long ; voir [Configuration](configuration.md) |

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Phil\Migration\Publisher` | Remplacé par un service propre à l'application pour envoyer ailleurs qu'à un transport de chatter ; `ChatterPublisher` par défaut, enregistré quand un canal est configuré |
| `Doctrine\Migrations\Version\MigrationFactory`, `Doctrine\Migrations\Migrator` | Ceux de l'application, donnés à DoctrineMigrationsBundle, restent ceux qui font le travail : le bundle les décore ; voir [Migrations](migrations.md#votre-propre-fabrique) |

## Migrations

Une migration annonce en étendant `AnnouncingMigration`, ou une classe de base propre à l'application qui implémente
`Announcing` et utilise le trait `Announces` ; voir [Migrations](migrations.md#votre-propre-classe-de-base).
