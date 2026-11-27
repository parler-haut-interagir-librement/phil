# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `phil/slug-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.2.1-alpha.

Development only.

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

## 0.2.0-alpha - 2026-11-18

The scope, the former slug and the subject of the history compare byte by byte on
MySQL and MariaDB. See [Upgrading from 0.1](doc/history.md#upgrading-from-01).

### Changed

- `SlugHistoryTable::configure()` takes the platform of the connection as its second argument, and the schema listener
  gives it: on MySQL and MariaDB, `scope`, `former_slug` and `subject` of `phil_slug_history` take the `utf8mb4`
  character set and the `utf8mb4_bin` collation. PostgreSQL and SQLite keep their default, which compares bytes
  already.

### Fixed

- Under the default case-insensitive collation of MySQL and MariaDB, recording a change of the subject `Post-7`
  rewrote the current slug of `post-7` and `forget()` removed its history, and a former slug of the scope `Blog`
  redirected to the subject of `blog`.

## 0.1.1-alpha - 2026-11-17

Documentation only.

### Changed

- `CONTRIBUTING.md` says what the package does and how to work on it, without the wording of its first drafts.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilSlugBundle` and the `phil_slug` configuration: named sluggers with `locale`, `max_length` and
  `leading_words`, and the `default` one. A leading word that is not one slug word is refused when the container is
  built.
- One service per slugger, `phil_slug.slugger.<name>`, to decorate; `Slugger` autowired to the default one, and
  `Slugger $<name>Slugger` to each.
- `Sluggers`, every slugger by name, as a public service.
- `SlugExtension`: the `phil_slug` Twig filter.
- The history of former slugs, optional: `phil_slug.history` with its `connection` and `clock`; the
  `SlugHistory` contract (`record()`, `currentSlug()`, `formerSlugs()`, `forget()`), which keeps no chain and drops a
  slug that becomes current again; `SlugHistoryKeys` and `InvalidSlugHistory` for the scope and the subject;
  `DoctrineSlugHistory` on the table `phil_slug_history`, `SlugHistoryTable` and `SlugHistorySchemaListener`, which
  adds the table to the schema the ORM generates. Tested on SQLite, MySQL 8 and PostgreSQL.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
