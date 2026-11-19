# Changelog

All notable changes to `phil/proposal-review` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `Campaign`, `Schedule`, `PhaseWindow` and `Phase`: dated, contiguous phases — submission, review, author response,
  deliberation — kept in UTC, read on a PSR-20 clock.
- `Criterion`, `Score` and `Scoring`: weighted criteria on their own scales, a review's score from 0 to 1, and its
  aggregation by mean or median.
- `ReviewPolicy`: blind review, minimum and maximum reviews, `Aggregation`, `Visibility` of the others' reviews,
  assigned reviewers only.
- `ReviewBoard`: proposals entered during submission; conflicts of interest by proposal or by author; manual and
  balanced assignment; reviews checked against the criteria; "save and next"; reviews and authors shown by the policy;
  the author response window; standings sorted by `\SortDirection`; decisions recorded during deliberation and applied
  together.
- `Pairwise\PairwiseVoting`: pairs drawn to balance exposure, never repeated for a voter, conflicts left out; votes in an
  append-only, numbered log; scores by `Pairwise\BradleyTerry`, reproducible from the log.
- Events with identifiers only: `PhaseChanged`, `ReviewSubmitted`, `DecisionRecorded`, `DecisionsApplied`,
  `PairwiseVoteCast`.
- Four store ports under `Contract\`, with adapters in memory and on Doctrine DBAL: tables built from their own schema
  under a checked prefix, identifiers compared byte for byte, instants as UTC text, votes numbered again when another
  request took the number, rows the stores did not write refused.
- Identifiers that look like an e-mail address are refused.
- A benchmark of the standings, the Bradley–Terry fit and the pair draw.
- The documentation in `doc/`: installation, usage, pairwise voting, algorithms and their guarantees, storage, events,
  security, extension points, testing and reference.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
