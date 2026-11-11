# Changelog

All notable changes to `phil/ddd-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Identifier` (`generate()`, `fromString()`, `fromUuid()`), `DomainEvent`, `DomainError`, `InvalidValue`.
- `Command`, `Query`, `CommandBus`, `QueryBus`, `CommandHandler`, `QueryHandler`.
- `IdentifierType` (DBAL, optional), unit-tested on five platforms, integration-tested on MySQL 8 and PostgreSQL. It
  works out whether a platform has a native UUID type once per platform object, in a `WeakMap`, and builds an
  identifier read from a binary column from the UUID it read, through `Identifier::fromUuid()`.
- `PhilDddBundle` (configuration `phil_ddd`); `MessengerCommandBus`, `MessengerQueryBus`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
