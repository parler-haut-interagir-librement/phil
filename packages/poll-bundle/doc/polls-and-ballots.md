# Polls and ballots

## Creating a poll

`Polls::create()` takes a `PollDraft` and the actor creating it, asks the policy's `mayCreate()`, and keeps the poll:

| `PollDraft` | Default | What it sets |
|---|---|---|
| `question` | — | A UTF-8 text of 1 to 500 characters. |
| `options` | — | 2 to 100 `PollOption`: an identifier of 1 to 64 ASCII letters, digits, `_` or `-`, and a label of 1 to 200 characters; the marks of a scale, written by `PollDraft::marks()`. |
| `kind` | `BallotKind::Single` | One option, several, options ranked, or one mark on a scale. |
| `minChoices`, `maxChoices` | 1, every option | The bounds of a multiple or ranked ballot; a single ballot and a scale ignore them. |
| `abstention` | `false` | Whether a blank ballot is an abstention or refused. |
| `counting` | the kind's default | The name of the counting method: `choices` for single and multiple ballots, `instant_runoff` for ranked ones, `scale` for a scale by default; `nps` for a Net Promoter Score. |
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

## Marks on a scale

A question that is not scored asks for a mark: how was the evening, from 1 to 5; how likely are you to recommend us,
from 0 to 10. Its options are its marks, which `PollDraft::marks($lowest, $highest, $labels)` writes, each labelled by
`$labels` under its mark or by the mark itself; a scale has 2 to 100 marks, negative ones included. A ballot holds one
mark, or none when the poll accepts a blank ballot.

```php
use Phil\Poll\BallotKind;

$evening = $polls->create(new PollDraft(
    'How was the evening?',
    PollDraft::marks(1, 5, [1 => 'Poor', 5 => 'Great']),
    BallotKind::Scale,
), $member);

$recommend = $polls->create(new PollDraft(
    'How likely are you to recommend us to a friend?',
    PollDraft::marks(0, 10),
    BallotKind::Scale,
    counting: 'nps',
), $member);

$polls->cast($recommend->id, $member, ['9']);
```

`scale` counts the distribution of the marks and their mean; `nps`, on a scale from 0 to 10 only, adds the promoters,
passives and detractors and the score: see [Results](results.md).

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

## A ballot and a closing at once

A ballot is written only while the poll is open, and the store checks it in the same write. `Polls` reads the poll and
refuses a ballot when it is not open; then `PollRepository::raiseRevisionWhileOpen()` keeps the ballot in a
transaction that first raises the poll's revision with an `UPDATE` whose `WHERE` holds the opening, the closing and
the closing by hand, and writes the ballot only when that statement changed the row. A ballot cast, changed, changed
by receipt or withdrawn while a manager closes the poll is therefore one of two things:

- kept before the closing: the `UPDATE` locks the poll's row until the ballot's commit, and the closing waits for it;
- refused with `VoteRefused` (`This poll is not open.`), when the closing committed first: nothing of the ballot is
  written, its participation included, and a changed ballot keeps its earlier choices.

The same holds for a new schedule and for a deletion committed meanwhile. A refusal is the domain error, never an
exception of the database. The instant compared is the one of the clock the bundle is given, in UTC to the second, as
the poll keeps its dates.

## Managing a poll

`close()`, `reschedule()` and `delete()` ask the policy's `mayManage()`. Closing a poll already over changes nothing;
rescheduling sets both dates, `null` for at once and for never, and refuses a closing at or before the opening;
deleting removes the poll with its ballots and participations.
