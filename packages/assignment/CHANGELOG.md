# Changelog

All notable changes to `phil/assignment` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `Problem`, `Slot`, `Applicant` and `Pair`: slots with a capacity and a minimum, applicants alone or in groups that
  stay together with their ranked choices, forbidden and forced applicant–slot pairs, checked whole at construction
  and kept in identifier order; identifiers that look like an e-mail address are refused.
- `Limits` on the size of a problem, and `WorkBudget` on the work of one assignment, with `BudgetExceeded` past it.
- `Seed`: a reproducible draw on PHP's `Xoshiro256StarStar` engine, from a text or from the system's secure source.
- `RankedRounds`: rounds of ranked choices in the same or alternating order, and serial dictatorship.
- `MinCostAssigner`: groups seated first, then a minimum-cost maximum flow solved by the primal-dual method, with
  `LinearRankCost` and `SquaredRankCost` behind `Contract\RankCost`.
- Slots under their minimum closed one at a time and the problem solved again, for every assigner.
- `Assignment`, `Placement`, `Unplaced` and `RefusedChoice`: each placement with its round and rank, each refused
  choice with its reason, the closed slots, the seed and draw order, and `toArray()` for an audit log.
- `ProblemReader`: a problem read from JSON or arrays, every field and bound checked.
- A fuzz target on the reader and the assigners, and a benchmark of a thousand people and a hundred slots.
- The documentation in `doc/`: installation, usage, algorithms and their guarantees, security, extension points,
  testing and reference.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
