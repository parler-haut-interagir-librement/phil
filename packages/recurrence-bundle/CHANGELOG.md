# Changelog

All notable changes to `phil/recurrence-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-24

### Changed

- The Node tests resolve `@hotwired/stimulus` to their stub with a synchronous hook given to `module.registerHooks()`,
  on the loading thread, instead of `module.register()`, which Node deprecates (DEP0205).
- Requires the latest minor of each dependency (`symfony/translation-contracts` 3.8), and of each development
  requirement (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).

## 0.1.1-alpha - 2026-11-17

Documentation only.

### Changed

- `CONTRIBUTING.md` says what the package does and how to work on it, without the wording of its first drafts.

## 0.1.0-alpha - 2026-11-11

### Added

- `RuleDescription`, which keeps one ICU formatter per language, pattern and time zone, 64 at most; `describe()` and the filter
  `rrule_text` take the time zone of the first occurrence, in which an end time is written as a day. The words of
  places from the end: *l’avant-dernier dimanche*, *le 3e jour en partant de la fin*, *the 3rd Saturday from the
  end*, *keeping the last and the first*.
- The ICU messages and labels in English and French, in `translations/`; the messages of places
  `recurrence.nth_day` and `recurrence.set_position` take `n` and `from_end`.
- `RecurrenceRuleColumnType`.
- `PhilRecurrenceBundle`, extension alias `phil_recurrence`.
- `RecurrenceRuleType` and `RecurrenceRuleTransformer`, which gives back the rule it showed when its fields come back
  meaning the same, so saving a form without touching a richer rule — *Friday the 13th*, set positions, a week
  start, an end time — keeps it as it was. The `interval` and `count` fields go from 1 to 9999, and a rule beyond
  makes the form invalid instead of being stored.
- The Stimulus controller `recurrence`, in `assets/`.
- `RecurrenceExtension`, with the Twig function `rrule_schema`: schema.org data for JSON-LD.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
