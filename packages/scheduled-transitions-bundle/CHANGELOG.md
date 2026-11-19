# Changelog

All notable changes to `phil/scheduled-transitions-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-18

The next version is 0.2.0-alpha: the keys of the scheduled transitions compare byte by byte on MySQL and MariaDB. See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Added

- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event, and its `FIELDS`: the fields of
  the bundle's entities that compare byte by byte on MySQL and MariaDB.

### Changed

- On MySQL and MariaDB, `id`, `resource_kind`, `resource_id` and `transition` of `phil_scheduled_transition` take the
  `utf8mb4` character set and the `utf8mb4_bin` collation in the schema the ORM generates, through
  `Infrastructure\Doctrine\BinaryKeysListener`. PostgreSQL and SQLite keep their default, which compares bytes already.
- `doctrine/dbal` is required, already required by `doctrine/orm`: `BinaryKeysListener` reads the platform of the
  connection.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, the transitions scheduled for the resource `Doc-A`
  were listed and cancelled for `doc-a`.

## 0.1.0-alpha - 2026-11-12

### Added

- The entity `ScheduledTransition`, with the column `claimed_until`; the enum `TransitionStatus`; the exceptions
  `SchedulerException`, `InvalidSchedule` and `NoHandler`; the port `ScheduledTransitionRepository`, which claims a
  due transition in one statement and records an outcome even once the entity manager is closed. Resources are
  `ResourceReference`s of `phil/resource`, a dependency, whose `InvalidResource` a malformed reference throws.
- `Scheduler`, which takes a required `ClockInterface`, claims each transition before applying it so that runs that
  overlap never apply one twice, and stops a run whose handler closed the entity manager once the attempt is saved;
  `TransitionHandler` and `RunReport`.
- `DoctrineScheduledTransitionRepository`, which records an outcome with one `UPDATE` and detaches the transition,
  so that a run costs one statement per transition rather than a flush over the whole batch.
- `PhilScheduledTransitionsBundle`, extension alias `phil_scheduled_transitions`, keys `frequency`, `batch`,
  `max_attempts` and `claim_for`; it wires the container's `clock` service.
- `WorkflowTransitionHandler`, `SubjectResolver` and `Subject`.
- `TransitionsSchedule`, `RunDueTransitions` and its handler.
- `RunTransitionsCommand`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
