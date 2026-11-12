# Changelog

All notable changes to `phil/scheduled-transitions-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
