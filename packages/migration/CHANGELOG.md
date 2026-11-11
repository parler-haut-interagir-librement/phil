# Changelog

All notable changes to `phil/migration` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `AnnouncingMigration`, the `Announcing` interface and the `Announces` trait: `note()` and `news()` in a migration.
- `Announcements`, kept by migration until the run is over, `Release`, `News`, `InvalidAnnouncement` and
  `AnnouncementsMissing`.
- `Channel` and `Content`: a transport, a locale, the notes or the news, titles numbered with `{counter}`, messages
  cut to a length.
- `Announcer`, `Publisher` and `ChatterPublisher`; a channel that fails is logged and the others are still told.
- `AnnouncingMigrationFactory`, which gives the announcements to every `Announcing` migration.
- `AnnouncingMigrator`, which announces a run once it is over: nothing on a dry run, nor on a failed run that was
  all or nothing; the migrations that stay on a failed run that was not.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
