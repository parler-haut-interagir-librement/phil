# Changelog

All notable changes to `phil/poll-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.4.0-alpha - 2026-11-18

The next version is 0.4.0-alpha: the identifiers, voters, receipts and subjects of the three tables compare byte by
byte on MySQL and MariaDB. See [Upgrading from 0.3](doc/installation.md#upgrading-from-03).

### Added

- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event, and its `FIELDS`: the fields of
  the bundle's entities that compare byte by byte on MySQL and MariaDB.

### Changed

- On MySQL and MariaDB, `id` and `subject` of `phil_poll`, `id`, `poll`, `voter` and `receipt` of `phil_poll_vote` and
  `poll` and `voter` of `phil_poll_participation` take the `utf8mb4` character set and the `utf8mb4_bin` collation in
  the schema the ORM generates, through `Infrastructure\Doctrine\BinaryKeysListener`, registered with the Doctrine
  store. PostgreSQL and SQLite keep their default, which compares bytes already.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, the voter `user:ann` was refused as having voted
  once `user:Ann` had, read the other's ballot as their own, and the polls of a subject were listed for another that
  differed from it only by case: the stores look voters and subjects up in SQL.

## 0.3.0-alpha - 2026-11-17

The next version is 0.3.0-alpha: `PollRepository` gains `raiseRevisionWhileOpen()`, which a store of polls of the
application implements. See [Upgrading a store from 0.2](doc/extension-points.md#upgrading-a-store-from-02).

### Added

- `PollRepository::raiseRevisionWhileOpen()`: runs the write of a ballot and raises the poll's revision in one
  transaction, only while the poll is open at the given instant, as the store reads it now; `false`, with nothing
  written, otherwise. `DoctrinePollRepository` checks the openness in the `WHERE` of the `UPDATE` that raises the
  revision and counts the row it changed; `InMemoryPollRepository` asks the poll it holds.

- Scales and Net Promoter Scores: `PollDraft::marks()`, the options of a scale with their labels; the counting methods
  `scale` and `nps` of `phil/poll` registered beside the others; the kind `scale` of `BallotView`, drawn by
  `@PhilPoll/ballot.html.twig` as a row of radio buttons, one per mark; `ResultsView::$rating` and
  `ResultsView::$netPromoter`, drawn by `@PhilPoll/results.html.twig` in the block `rating` as the mean mark and as the
  score with its promoters, passives and detractors, the marks listed from the lowest and none of them winning; the
  words of the domain `phil_poll` for them, and `number.decimal` and `number.thousands`, the separators of the numbers
  written, in English and French.
- The variable `errors` of `@PhilPoll/ballot.html.twig`: the messages of a ballot refused, drawn by the recipe
  `Field:Error` in an alert the fieldset names with `aria-describedby`, the fieldset marked `aria-invalid`.
- The browser suite: each kind of ballot filled and sent with the keyboard in Chrome, with the PHIL UX kit and with the
  Shadcn kit, its alert, its disabled form, and the accessible names and roles of its parts.

### Changed

- A closing and a new schedule raise the revision of the poll, as a ballot does, so that its kept counts are read
  again; deleting a poll removes the counts kept for its current revision from the cache pool.
- Each bar of `@PhilPoll/results.html.twig` is given its share of the highest score, from 0 to 100, and the score as
  its `aria-valuetext`.
- `phil/poll: ^0.1.1` is required, for its scales.

### Fixed

- A ballot cast, changed, changed by receipt or withdrawn while the poll is being closed is either kept before the
  closing or refused with `VoteRefused` (`This poll is not open.`): it no longer commits after the closing and changes
  the result. The same holds for a new schedule and a deletion committed meanwhile. A changed ballot refused this way
  keeps its earlier choices, and an anonymous ballot refused leaves no participation.

- With the recipes of the Shadcn kit, `@PhilPoll/ballot.html.twig` renders: each radio button is given its name, its
  checked state and whether it is required, which the Shadcn group does not pass to its buttons; each bar of the
  results is a percentage, as the Shadcn `Progress` reads it.
- The blank ballot of a voter who cast one is checked on the form.

## 0.2.0-alpha - 2026-11-14

### Added

- The Twig function `phil_poll_kit()`, the name under which a recipe of the kit is installed, and the option
  `kit_subdirectory`, the subdirectory of the recipes under the components directory, read from the `ux_toolkit`
  configuration when null.

### Changed

- `@PhilPoll/ballot.html.twig` and `@PhilPoll/results.html.twig` draw their parts with the recipes `Field`,
  `RadioGroup`, `Checkbox`, `NativeSelect`, `Button` and `Progress`, installed from the PHIL UX kit or from the Shadcn
  kit, so a site on Tailwind draws them too; the application installs the recipes and Symfony UX Twig Component. The
  blocks `choices`, `status`, `actions`, `lines` and `revealed` stay, and the map `kit` of the recipes' names is
  theirs to use.
- `symfony/ux-twig-component: ^3.7` is required, no longer suggested: the templates do not render without it.

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
