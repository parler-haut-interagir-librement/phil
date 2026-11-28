# Changelog

All notable changes to `phil/rules` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- The condition tree, `All`, `Any`, `Not` and `Leaf`, checked into a `Rule` against a registry of condition types and
  `Limits`: unknown types, refused operands, empty `All` or `Any` and other node classes refused when the rule is built.
- `ConditionType`, `ConditionTypes` and the operand schemas, `Operand`, `OperandKind`, `OperandSchema` and `Operands`:
  stable type names, ten operand kinds with their bounds, unknown and missing operands refused, `describe()` for
  builders and API descriptions.
- The built-in types `fact.equals`, `fact.in`, `fact.contains`, `fact.compare`, `fact.present` and `date.compare`.
- `FactSource` and `Facts`: facts passed by the caller, as values or closures computed once when first read.
- Evaluation with short-circuit, `Result`, `Step`, `Verdict`, `Outcome` and `NodeKind`: the trace of every condition
  evaluated or skipped, with the JSON Pointer of its node, and the leaves that decided the answer.
- `Limits` on the bytes, the depth, the conditions and the evaluations of a rule, with `LimitExceeded` past each bound.
- The versioned JSON form, `RuleReader` and `RuleWriter`: strict reading, canonical writing that reads back to itself,
  and the fingerprint of a rule.
- The exceptions `RulesException`, `InvalidRule`, `LimitExceeded`, `InvalidConditionType`, `InvalidLimit`,
  `MissingFact` and `MissingOperand`.
- The fuzz target of the reader and the benchmark of reading and evaluating.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
