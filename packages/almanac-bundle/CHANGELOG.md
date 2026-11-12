# Changelog

All notable changes to `phil/almanac-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
