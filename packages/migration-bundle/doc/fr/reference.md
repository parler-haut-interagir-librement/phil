<!-- phil-dev source-sha256=1b9b8a177814f179d1f9db83f0f66d5937152219fd9610c40e0a08ab7eca7001 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\MigrationBundle\PhilMigrationBundle` | `AbstractBundle` | La configuration `phil_migration`, les services, la passe de compilation |
| `Phil\MigrationBundle\Infrastructure\DependencyInjection\AnnouncementServices` | `final class` | Enregistre les annonces, l'annonceur et l'éditeur |
| `Phil\MigrationBundle\Infrastructure\DependencyInjection\AnnouncingMigrationsPass` | passe de compilation | Enveloppe la fabrique de migrations et le migrateur de DoctrineMigrationsBundle |

## Services

| Identifiant | Classe | Quand |
|---|---|---|
| `Phil\Migration\Announcements` | `Announcements` | Toujours |
| `Phil\Migration\Publisher` | `ChatterPublisher` | Avec des canaux ; remplacez-le pour envoyer ailleurs |
| `Phil\Migration\Announcer` | `Announcer` | Avec des canaux |
| `phil_migration.migration_factory` | `AnnouncingMigrationFactory` | Avec DoctrineMigrationsBundle |
| `phil_migration.migrator` | `AnnouncingMigrator` | Avec DoctrineMigrationsBundle et des canaux |

## Constantes

| Constante | Valeur |
|---|---|
| `AnnouncingMigrationsPass::DEPENDENCY_FACTORY` | `doctrine.migrations.dependency_factory` |
| `AnnouncingMigrationsPass::FACTORY_ID` | `phil_migration.migration_factory` |
| `AnnouncingMigrationsPass::MIGRATOR_ID` | `phil_migration.migrator` |
| `AnnouncingMigrationsPass::MIGRATION_TAG` | `doctrine_migrations.migration`, les migrations du conteneur |
| `AnnouncementServices::MIN_LENGTH` | `100`, le message le plus court |
| `AnnouncementServices::CONTENTS` | `notes`, `news` |

Les types des annonces et des canaux sont ceux de `phil/migration` : voir sa référence.
