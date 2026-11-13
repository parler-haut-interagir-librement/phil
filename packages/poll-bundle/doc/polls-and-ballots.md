# Polls and ballots

## Creating a poll

`Polls::create()` takes a `PollDraft` and the actor creating it, asks the policy's `mayCreate()`, and keeps the poll:

| `PollDraft` | Default | What it sets |
|---|---|---|
| `question` | — | A UTF-8 text of 1 to 500 characters. |
| `options` | — | 2 to 100 `PollOption`: an identifier of 1 to 64 ASCII letters, digits, `_` or `-`, and a label of 1 to 200 characters. |
| `kind` | `BallotKind::Single` | One option, several, or options ranked. |
| `minChoices`, `maxChoices` | 1, every option | The bounds of a multiple or ranked ballot; a single ballot ignores them. |
| `abstention` | `false` | Whether a blank ballot is an abstention or refused. |
| `counting` | the kind's default | The name of the counting method: `choices` for single and multiple ballots, `instant_runoff` for ranked ones by default. |
| `anonymous` | `false` | Whether ballots are kept apart from their voters: see [Anonymity](anonymity.md). |
| `results` | `ResultsVisibility::Always` | Who sees the results: see [Results](results.md). |
| `subject` | `null` | The resource of the application the poll belongs to. |
| `opensAt`, `closesAt` | `null` | When the poll opens and closes; at once and never by default. |
| `seats` | 1 | The seats the count fills, 1 to one fewer than the options; above 1 for a method that fills several, such as `meek_stv`: see [Single transferable vote](single-transferable-vote.md). |

The counting method must count the poll's kind of ballot. The options and the rules never change once the poll is
created, so that every ballot cast stays valid; to change them, create another poll. The order of the options is
kept: it orders the options tied in the results, and breaks the last ties of an instant runoff. An application that
wants a lot declares them in a random order.

`get()` finds a poll by its identifier, `ofSubject()` the polls of a resource, oldest first.

## Open and closed

A poll is open from its opening — from its creation when it has none — until its closing, or until a manager closes
it by hand. Instants are compared to the second, in UTC: a poll closing at 18:00:00 accepts a ballot at 17:59:59 and
refuses one at 18:00:00.

## Casting, changing, withdrawing

| Method | What it does |
|---|---|
| `cast($poll, $voter, $choices)` | Checks the choices against the rules and keeps the ballot. Returns a `CastVote`: the ballot's identifier and, for an anonymous poll, its receipt. |
| `change($poll, $voter, $choices)` | Replaces the voter's ballot in a poll that is not anonymous. |
| `changeWithReceipt($poll, $voter, $receipt, $choices)` | Replaces the anonymous ballot of this receipt. |
| `withdraw($poll, $voter)` | Removes the voter's ballot from a poll that is not anonymous; they may cast another one. |
| `hasVoted($poll, $voter)` | Whether the voter cast a ballot, anonymous or not. |
| `ballotOf($poll, $voter)` | The voter's ballot in a poll that is not anonymous. |
| `ballotOfReceipt($poll, $receipt)` | The anonymous ballot of this receipt. |

Each of the first four refuses with `VoteRefused` when the poll is not open or the policy's `mayVote()` refuses the
voter, and with `InvalidBallot`, from `phil/poll`, when the choices break the rules: a choice that is not an option,
a choice given twice, too few or too many. The choices are given as a form or a request gives them, an array of
anything; the checks are the library's.

A voter has one ballot per poll: a second `cast()` throws `AlreadyVoted`. Two requests that race to cast the same
voter's ballot are stopped by the unique constraints of the tables.

## Managing a poll

`close()`, `reschedule()` and `delete()` ask the policy's `mayManage()`. Closing a poll already over changes nothing;
rescheduling sets both dates, `null` for at once and for never, and refuses a closing at or before the opening;
deleting removes the poll with its ballots and participations.
