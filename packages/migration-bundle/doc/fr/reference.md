<!-- phil-dev source-sha256=f8cb8bd1cefbab613ef7a4dd9105960d8d373e75cee8fdc28e262fb283c02043 -->
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
| `AnnouncementServices::MIN_LENGTH` | `100`, le message le plus court |
| `AnnouncementServices::CONTENTS` | `notes`, `news` |

Les types des annonces et des canaux sont ceux de `phil/migration` : voir sa référence.
