# Changelog

All notable changes to `phil/poll` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `BallotKind`: single, multiple and ranked ballots.
- `PollRules`: the kind, 2 to 100 options identified by 1 to 64 ASCII letters, digits, `_` or `-`, the minimum and
  maximum of choices and the abstention; `single()`, `multiple()`, `ranked()`, `of()`, `has()`, and `ballot()`, which
  checks the values of a form or a request.
- `Ballot`: the choices of one voter in the order of preference, an abstention when empty.
- `CountingMethod`, the port of a way of counting; `ChoiceCount` (plurality and approval), `InstantRunoff` (rounds,
  exhausted ballots, ties broken by the rounds before, then by the declared order, a tie of every option kept),
  `Borda` and `Schulze` (winning votes, places counting the options that beat an option).
- `Result`, `Standing`, `Round` and `Tally`: the ballots and abstentions counted, the standings with shared places,
  `winners()`, `standingOf()` and the rounds of an instant runoff.
- `CountingMethods`: the methods of an application by name, each declared once; `defaults()`, `defaultFor()`,
  `get()`, `has()`, `namesFor()` and `count()`.
- `PollException`, implemented by `InvalidPoll`, `InvalidBallot` and `UnknownCountingMethod`.
- The benchmark of the four counts over ten thousand ballots and of the check of a ballot.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
