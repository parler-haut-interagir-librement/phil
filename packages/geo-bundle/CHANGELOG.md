# Changelog

All notable changes to `phil/geo-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

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
