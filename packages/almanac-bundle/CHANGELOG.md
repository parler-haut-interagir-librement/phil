# Changelog

All notable changes to `phil/almanac-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-20

The key of a stored date compares byte by byte on MySQL and MariaDB. See
[Upgrading from 0.1](doc/stored-dates.md#upgrading-from-01).

### Added

- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event and registered with
  `database: true`, and its `FIELDS`: the fields that compare byte by byte on MySQL and MariaDB.

### Changed

- On MySQL and MariaDB, `date_key` of `phil_almanac_date` takes the `utf8mb4` character set and the `utf8mb4_bin`
  collation in the schema the ORM generates, through `Infrastructure\Doctrine\BinaryKeysListener`. PostgreSQL and
  SQLite keep their default, which compares bytes already. The migration, which `doctrine:migrations:diff` writes:

  ```sql
  -- MySQL and MariaDB
  ALTER TABLE phil_almanac_date MODIFY date_key VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
  -- PostgreSQL and SQLite: nothing to run.
  ```

  Apply it with `doctrine:migrations:migrate`, or run the statement by hand on MySQL and MariaDB; see the
  [upgrade guide](doc/stored-dates.md#upgrading-from-01).

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, a row written `Harvest` or `harvést` outside the
  bundle was found for the date `harvest`, which then took its rules, and stopped `harvest` from being saved.

## 0.1.0-alpha - 2026-11-12

### Added

- `Domain\Model\StoredDate` on `phil_almanac_date`, the port `StoredDateRepository` and `DoctrineStoredDateRepository`,
  which reads the table once until a date is saved or removed through it, or `reset()` — tagged `kernel.reset` — is
  called.
- `Application\ConfiguredDates`, `Definitions`, `AlmanacFactory` and the service `Phil\Almanac\Almanac`, built when
  asked for; `DateDraft`.
- `PhilAlmanacBundle`, extension alias `phil_almanac`: `timezone`, `database` and `dates` — title, rule, until, days,
  all day, tags —, each date read when the container is built, its key kept as written; `eclipses`, which declares
  `solar-eclipse` and `lunar-eclipse` at their instant; `places`, by latitude, longitude and time zone, and the
  service `Application\Places`.
- The Twig function `almanac_dates`, which takes any `DateTimeInterface` for its bounds; `sun_times` and
  `almanac_eclipses`, of `Infrastructure\Twig\SkyExtension`.
- `StoredDateType` and its tags transformer, which keeps each tag once; validation messages in English and French.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
