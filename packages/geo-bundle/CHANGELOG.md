# Changelog

All notable changes to `phil/geo-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-25

### Added

- `phil_geo.http`: `client`, the HTTP client of the geocoders, of ipstack and of the MaxMind updater (`http_client` by
  default), `timeout` (3 seconds) and `max_duration` (5 seconds), handed to each adapter as its `Timeouts`;
  `DatabaseUpdater::TIMEOUT`, 30 seconds of wait for a byte of the download. `GeocodingServices::register()` takes the
  `http` configuration, last and optional.

### Changed

- A test of a directory that cannot be written is skipped when the tests run as a user who writes there anyway, such as
  root in a container.
- The Node tests resolve `@hotwired/stimulus` to their stub with a synchronous hook given to `module.registerHooks()`,
  on the loading thread, instead of `module.register()`, which Node deprecates (DEP0205).
- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, …).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

### Security

- The geocoders and ipstack ran on `http_client` without a timeout: a slow provider held a worker until
  `default_socket_timeout`, 60 seconds. They take the timeouts of `phil_geo.http`.

## 0.1.0-alpha - 2026-11-11

### Added

- `Coordinates` as a Doctrine embeddable, `config/doctrine/Coordinates.orm.xml`.
- `PhilGeoBundle`, extension alias `phil_geo` (`doctrine_mapping`).
- Geocoding: `phil_geo.geocoding` — `providers` (`nominatim`, `google_maps`, `opencage`, `here`, `mapbox`,
  `azure_maps`, `adresses_quebec`, `photon`, `nrcan`), their options and a `cache` — and `phil_geo.ip_locator.ipstack`,
  whose `base_url` defaults to `https://api.ipstack.com`; `GeocodingServices` registers the providers, the chain, the
  cache, `Geocoder` and `IpLocator` on `http_client`. The keys are required for `opencage`, `here`, `mapbox` and
  `azure_maps`; `phil_geo.geocoding.photon` (`base_url`, `limit`, `languages`) needs no key, nor
  `phil_geo.geocoding.nrcan` (`base_url`, `limit`), Natural Resources Canada. The configuration refuses a
  `cache.pool` when the providers include `mapbox` without `permanent`, whose terms forbid keeping its temporary
  answers.
- `phil_geo.ip_locator.maxmind` (`database`, `locale`): a MaxMind city database on the server as the `IpLocator`,
  before ipstack; `Database` autowired.
- `CoordinatesType` and `CoordinatesTransformer`.
- `GeoExtension`: `geo_distance()`, `format_distance`, `geo_attributes()`, and the Twig function `geo_schema`:
  schema.org data for JSON-LD. `format_distance` builds one number formatter per locale and keeps those of 64
  locales at most, and rounds before choosing the unit: 999.6 m is `1 km`.
- The Stimulus controller `distance-sort` and its module `distance.js`, in `assets/`; `formatDistance()` rounds
  before choosing the unit and reads a Symfony locale such as `fr_CA` as `fr-CA`.
- `phil:geo:maxmind:update` and `DatabaseUpdater`: the latest MaxMind database downloaded with the account
  (`account_id`, `license_key`, `edition`, `download_url`), checked against its SHA-256, read out of its archive and
  renamed over the old one; nothing downloaded when it is already the latest. Both are registered only with
  `account_id` and `license_key`, so a database that is only read needs no `http_client`; the account takes both
  settings, and a `database`, or the configuration is refused. `DatabaseUpdater` holds a lock on `<database>.lock`
  and writes its archive and extracted file under names of their own, so two runs never remove or read each other's
  files; the second fails at once. Its licence key is `#[SensitiveParameter]`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
