# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\MigrationBundle\PhilMigrationBundle` | `AbstractBundle` | The `phil_migration` configuration, the services, the compiler pass |
| `Phil\MigrationBundle\Infrastructure\DependencyInjection\AnnouncementServices` | `final class` | Registers the announcements, the announcer and the publisher |
| `Phil\MigrationBundle\Infrastructure\DependencyInjection\AnnouncingMigrationsPass` | compiler pass | Wraps the migration factory and the migrator of DoctrineMigrationsBundle |

## Services

| Id | Class | When |
|---|---|---|
| `Phil\Migration\Announcements` | `Announcements` | Always |
| `Phil\Migration\Publisher` | `ChatterPublisher` | With channels; replace it to send elsewhere |
| `Phil\Migration\Announcer` | `Announcer` | With channels |
| `phil_migration.migration_factory` | `AnnouncingMigrationFactory` | With DoctrineMigrationsBundle |
| `phil_migration.migrator` | `AnnouncingMigrator` | With DoctrineMigrationsBundle and channels |

## Constants

| Constant | Value |
|---|---|
| `AnnouncingMigrationsPass::DEPENDENCY_FACTORY` | `doctrine.migrations.dependency_factory` |
| `AnnouncingMigrationsPass::FACTORY_ID` | `phil_migration.migration_factory` |
| `AnnouncingMigrationsPass::MIGRATOR_ID` | `phil_migration.migrator` |
| `AnnouncementServices::MIN_LENGTH` | `100`, the shortest message |
| `AnnouncementServices::CONTENTS` | `notes`, `news` |

The types of announcements and channels are `phil/migration`'s: see its reference.
