# Changelog

All notable changes to `phil/poll-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `PhilPollBundle`, configured under `phil_poll`: the store (`doctrine` or `memory`), the entity manager, the
  policy and the cache of the counts (`results_cache`); the Doctrine mapping prepended.
- `Poll`: the question, the labelled options as `PollOption`, the rules, the counting method, the seats, the
  anonymity, the `ResultsVisibility`, the subject, the opening and the closing, and the revision of its ballots;
  `isOpenAt()`, `isClosedAt()`, `close()` and `reschedule()`, to the second in UTC.
- `Vote`: a named ballot with its voter and time, or an anonymous one with the hash of its receipt only;
  `Participation`, the record that a voter took part in an anonymous poll, without time.
- `PollRepository` and `VoteRepository`, with Doctrine stores — a new ballot and its participation in one
  transaction, unique constraints against a second ballot, the ballots of a count streamed row by row, the revision of
  the ballots raised by one statement — and in-memory ones, tagged `kernel.reset` and emptied between requests.
- `Polls`: `create()`, `get()`, `ofSubject()`, `cast()`, `change()`, `changeWithReceipt()`, `withdraw()`,
  `hasVoted()`, `ballotOf()`, `ballotOfReceipt()`, `close()`, `reschedule()`, `delete()`, `turnout()`, `results()`,
  `resultsFor()`, `election()`, `electionFor()` and `maySeeResults()`, the counts kept in a cache pool until the next
  ballot when one is named; `PollDraft`, with its seats, and `CastVote`.
- `MeekStv`, the single transferable vote by Meek's method, `meek_stv`, in exact numbers of denominator one billion
  (`Billionths`), with the rounds published as `Election`, `ElectionRound` and `CandidateTally`, `CandidateStatus` and
  `TieBreak`; `ElectingMethod`, the contract of a method that fills several seats; a fuzz target and a benchmark.
- The voting interface: `PollViews`, with `BallotView`, `BallotChoice`, `ResultsView` and `ResultLine`; `BallotForm`,
  the choices read from a posted form; the templates `@PhilPoll/ballot.html.twig` and `@PhilPoll/results.html.twig`,
  in the markup of the PHIL UX kit's recipes, with the words of the domain `phil_poll` in English and French; the Twig
  functions `phil_poll_ballot()` and `phil_poll_results()`.
- The Stimulus controller `phil--poll-bundle--results`, which dispatches the DOM event `poll:revealed` when the
  results of a closed poll are revealed, and the block `revealed`, for the kit's celebration.
- `PollPolicy`, the port of who may create, vote and manage; `DefaultPollPolicy`, everyone voting and the creator
  managing.
- The counting methods of the library and `MeekStv` as services, and every service implementing `CountingMethod`
  added beside them.
- The events `PollCreated`, `VoteCast`, `VoteChanged`, `VoteWithdrawn`, `PollClosed` and `PollDeleted`, each naming
  the poll only, dispatched through the PSR-14 dispatcher when there is one.
- `PollBundleException`, implemented by `InvalidPollDefinition`, `AlreadyVoted`, `NotFound`, `VoteRefused` and
  `PollRefused`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
