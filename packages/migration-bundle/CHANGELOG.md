# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `phil/migration-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.3-alpha.

Development only.

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4).

## 0.1.2-alpha - 2026-11-18

A note that several migrations of a run bring is announced once on each channel.
See [A migration that announces](doc/migrations.md#a-migration-that-announces).

### Fixed

- A note that two migrations of one `doctrine:migrations:migrate` bring, such as `* Libraries updated.` added by a
  helper of the application, is announced once on each channel, where it first comes, instead of twice. It requires
  `phil/migration` `^0.1.1@dev`, which gives each note, and each piece of news, of a run once.

## 0.1.1-alpha - 2026-11-14

### Fixed

- The bundle installs beside DoctrineMigrationsBundle 4.0, the version a Symfony 8.2 application gets: it requires
  `doctrine/doctrine-migrations-bundle` `^4.0`. Of the 3.x versions, only 3.7.0 accepted Symfony 8, and only because
  it did not declare its dependency on `symfony/http-kernel`.
- The migrations of the container, with `enable_service_migrations`, get the announcements: each `Announcing` one
  tagged `doctrine_migrations.migration` is given them through `announceWith()` as it is built, instead of failing
  with `AnnouncementsMissing`.
- The migration factory and the migrator are decorated after the other decorators of the same service, with the
  priority `-1`, so that what the factory of the service migrations returns gets the announcements too.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilMigrationBundle` and the `phil_migration` configuration: channels by name with `transport`, `locale`,
  `content`, `title`, `continued`, `heading`, `link` and `max_length`.
- `AnnouncementServices`: the announcements, and, with channels, the announcer and `ChatterPublisher` on the chatter.
- `AnnouncingMigrationsPass`: the migration factory and the migrator of DoctrineMigrationsBundle wrapped, or the
  ones the application gave in `doctrine_migrations.services` decorated.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
