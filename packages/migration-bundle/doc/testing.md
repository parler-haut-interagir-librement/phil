# Testing

| Area | What is covered |
|---|---|
| `PhilMigrationBundle`, `AnnouncementServices` | Without channels only the announcements; channels as `Channel` definitions with their defaults, the chatter publisher, the logger; what the configuration refuses |
| `AnnouncingMigrationsPass` | A factory or a migrator the application gave decorated, the ones Doctrine Migrations makes wrapped, method calls that give nothing ignored, the decorations after the others, the `Announcing` migrations of the container given the announcements and the others left alone, no migrator without channels, nothing without DoctrineMigrationsBundle, the pass registered |
| A whole application | FrameworkBundle, DoctrineBundle on SQLite in memory and DoctrineMigrationsBundle: a dry run announces nothing, a run of two migrations of a directory and one of the container is one message per channel |

```sh
vendor/bin/phpunit packages/migration-bundle/tests
```
