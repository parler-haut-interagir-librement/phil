# Changelog

All notable changes to `phil/ddd-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.2.1-alpha.

Documentation only.

### Changed

- The documentation of the buses tells how failures come out on a Messenger bus with `unwrap_exceptions`: the domain
  error of a command and the failure of a query come out the same, and a command's other failure unwrapped.
- The documentation of the buses tells how to rename a queued command with Messenger's `serializedTypeNameAliases`.

## 0.2.0-alpha - 2026-11-26

### Added

- `IdentifierValueResolver`: a controller argument typed with an identifier class receives the request attribute of
  the same name, a route placeholder, as that class; any other value is a 404. Registered when `symfony/http-kernel`
  is installed, under its class name, so that `#[ValueResolver(IdentifierValueResolver::class, disabled: true)]`
  opts an argument out, in debug mode too.
- `IdentifierNormalizer`: the Serializer writes an identifier as its canonical string and builds it back to the
  requested class; any other value is a `NotNormalizableValueException` whose message may be shown to the user.
  Registered when `symfony/serializer` is installed.
- `symfony/http-kernel` and `symfony/serializer` in `suggest`, with a `conflict` on the versions below 8.2.
- `make:ddd:context`, `make:ddd:identifier`, `make:ddd:command`, `make:ddd:query` and `make:ddd:event`: with
  `symfony/maker-bundle`, they write a bounded context (`src/<Context>/{Domain,Application,Infrastructure}` and a
  README), an identifier and its Doctrine type, a command or a query with its handler, and a domain event, never over
  an existing file. `DddSkeleton` renders the files from the templates of `templates/maker/`. Registered when
  MakerBundle is installed; `symfony/maker-bundle` in `suggest`, with a `conflict` on the versions below 1.68.

### Changed

- With `symfony/serializer` installed, an identifier is serialised as its string instead of `{"value": …}`.
- The development requirements ask for the latest minor of each dependency (`doctrine/dbal` 4.6).

## 0.1.1-alpha - 2026-11-17

Documentation only.

### Changed

- `CONTRIBUTING.md` says what the package does and how to work on it, without the wording of its first drafts.

## 0.1.0-alpha - 2026-11-11

### Added

- `Identifier` (`generate()`, `fromString()`, `fromUuid()`), `DomainEvent`, `DomainError`, `InvalidValue`.
- `Command`, `Query`, `CommandBus`, `QueryBus`, `CommandHandler`, `QueryHandler`.
- `IdentifierType` (DBAL, optional), unit-tested on five platforms, integration-tested on MySQL 8 and PostgreSQL. It
  works out whether a platform has a native UUID type once per platform object, in a `WeakMap`, and builds an
  identifier read from a binary column from the UUID it read, through `Identifier::fromUuid()`.
- `PhilDddBundle` (configuration `phil_ddd`); `MessengerCommandBus`, `MessengerQueryBus`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
