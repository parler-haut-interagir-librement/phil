# Changelog

All notable changes to `phil/migration-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilMigrationBundle` and the `phil_migration` configuration: channels by name with `transport`, `locale`,
  `content`, `title`, `continued`, `heading`, `link` and `max_length`.
- `AnnouncementServices`: the announcements, and, with channels, the announcer and `ChatterPublisher` on the chatter.
- `AnnouncingMigrationsPass`: the migration factory and the migrator of DoctrineMigrationsBundle wrapped, or the
  ones the application gave in `doctrine_migrations.services` decorated.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
