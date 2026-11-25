# Changelog

All notable changes to `phil/url-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.4-alpha.

Development only.

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).

## 0.1.3-alpha - 2026-11-18

### Added

- The configuration key `phil_url.column_type`, `null` by default: `null` registers the column type `phil_url` when
  `doctrine/dbal` and DoctrineBundle are installed, `true` requires them and refuses to build the container, naming the
  missing package, when one is absent, `false` never registers it.

### Fixed

- The bundle boots without `doctrine/dbal`: `PhilUrlBundle::boot()` and `shutdown()` touch `UrlColumnType` only when
  that package is installed, and the column type is registered only then. `doctrine/dbal` stays a suggestion.

## 0.1.2-alpha - 2026-11-15

### Fixed

- `PhilUrlBundle::shutdown()` sets `UrlColumnType::allowLocalhost()` back to `false`: a kernel booted after one that
  allowed `localhost`, or a test that runs after it in the same process, no longer inherits its switch.

## 0.1.1-alpha - 2026-11-11

### Added

- The configuration key `phil_url.allow_localhost`, `false` by default, also a container parameter: `true`, in
  development and tests, lets `TypedUrlType`, `UrlOfTypeValidator`, `UrlColumnType` and `UrlExtension` read
  `localhost` and the names under `.localhost`; an IP address and any other single name stay refused. Each takes it as
  `allowLocalhost`, `TypedUrlTransformer` too; `UrlColumnType::allowLocalhost()` is set when the bundle boots.

### Changed

- The package requires `phil/url` 0.1.3, which refuses the names under `.localhost` unless allowed.

## 0.1.0-alpha - 2026-11-11

### Added

- `AcceptedUrlTypes`, the accepted kinds as enum sets.
- `UrlColumnType` (`phil_url`).
- `PhilUrlBundle`, extension alias `phil_url`.
- `UrlExtension`.
- `TypedUrlType` and `TypedUrlTransformer`.
- `UrlOfType`, whose `@param` names the `EnumSet` it accepts, and its validator.
- The messages in English and French, in `translations/`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
