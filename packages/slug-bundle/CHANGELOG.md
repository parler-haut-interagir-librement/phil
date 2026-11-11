# Changelog

All notable changes to `phil/slug-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
