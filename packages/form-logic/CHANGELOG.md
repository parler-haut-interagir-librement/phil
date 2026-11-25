# Changelog

All notable changes to `phil/form-logic` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.3-alpha.

Development only.

### Changed

- The development requirements ask for the latest minor of each dependency (`twig/twig` 3.31,
  `symfony/stimulus-bundle` 3.8).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

## 0.1.2-alpha - 2026-11-20

The archive Composer installs holds the tests again.

### Changed

- The tests, the test cases of JsonLogic with their MIT notice among them, and the development files are in the
  archive Composer installs, as in the other packages: `.gitattributes` is gone.

## 0.1.1-alpha - 2026-11-18

### Changed

- `phil/forms-bundle` 0.2 is accepted beside 0.1.

## 0.1.0-alpha - 2026-11-14

### Added

- The expressions, immutable and checked when built: `Literal`, `ListOf`, `Variable`, `Missing`, `Comparison`,
  `Between`, `Not`, `AllOf`, `AnyOf`, `Conditional`, `In` and `Arithmetic`, each written as a JsonLogic rule.
- `JsonLogicReader`, which reads the JsonLogic subset — `var`, `missing`, `missing_some`, `==`, `!=`, `===`, `!==`,
  `<`, `<=`, `>`, `>=`, `!`, `!!`, `and`, `or`, `if`, `in`, `+`, `-`, `*`, `/`, `%`, `min`, `max` — as JSON or decoded,
  refuses any other operator, and bounds the bytes, depth, nodes, lists and texts of a rule by its `Limits`.
- `JsonLogicWriter`, which writes the canonical rule the reader reads back.
- `Evaluator`, with the conversions of JsonLogic: equality and order as JavaScript's, texts ordered by UTF-16 code
  units, text read as a number in decimal notation only, non-finite results as `null`, numbers written as JavaScript
  writes them.
- `TypeChecker`, `Schema`, `VariableDefinition` and `VariableType`: a rule checked against the types of the variables
  it reads, and `VariableNames`, the variables it reads.
- `FormLogic`, the field conditions and computed variables of a form in order, each reading only the steps before it,
  checked against the fields, written for storage and for the browser and read back.
- `Judge` and `Verdict`: the server's judgement of a submitted answer, hidden fields dropped in cascade, computed
  variables to store with the answer.
- The `logic` Stimulus controller and its evaluator `logic.js`: fields shown and hidden, their controls disabled, and
  computed values shown as the form is filled; an unreadable rule leaves every field shown.
- `JsonLogicConditions`, the `ConditionEvaluator` of `phil/forms-bundle`, its fields typed by what they take; the
  controller applies the conditions of that bundle's pages in the browser, from the steps its `LogicSteps` writes,
  checked against the bundle's own judgement.
- The shared cases of `tests/Fixtures/cases.json`, run by PHPUnit and by Node; the test cases JsonLogic publishes,
  kept with their MIT notice in `tests/Fixtures/json-logic/`, run by both, each refusal and each difference on values
  listed with its reason in `differences.json`; the browser suite of the controller in an application with
  AssetMapper and StimulusBundle, under Panther; the fuzz target of the reader; the benchmarks of reading, evaluating
  and judging. `.gitattributes` leaves the tests out of the archive.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
