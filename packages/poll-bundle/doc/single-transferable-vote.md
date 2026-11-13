# Single transferable vote

A poll that fills several seats — a board of five, the three games of the next convention — is counted by the single
transferable vote. The bundle counts it by Meek's method, `meek_stv`, on ranked ballots, in exact numbers, and
publishes every round, so that anyone who holds the ballots can replay the count and find the same figures.

## A poll with seats

```php
use Phil\Poll\BallotKind;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Domain\Counting\MeekStv;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;

$poll = $polls->create(new PollDraft(
    'Who sits on the board?',
    [PollOption::of('ann', 'Ann'), PollOption::of('bob', 'Bob'), PollOption::of('cat', 'Cat'), PollOption::of('dan', 'Dan')],
    BallotKind::Ranked,
    counting: MeekStv::NAME,
    results: ResultsVisibility::AfterClosing,
    seats: 2,
), $member);
```

`seats` is 1 to one fewer than the options. A poll whose method does not fill seats refuses more than one with
`InvalidPollDefinition`; `meek_stv` with one seat is an instant runoff counted in exact numbers.

## The election and its rounds

`Polls::election($poll)` counts the ballots into an `Election`: the method, the seats, the ballots and abstentions
counted, the options elected in the order of their election, and the rounds. `electionFor($poll, $viewer)` counts for
a viewer, as `resultsFor()` does. `results($poll)` gives the same count as a `Result` of `phil/poll`: the elected
options share the first place.

Each `ElectionRound` publishes:

| Field | What it holds |
|---|---|
| `number` | The round, from 1. |
| `iterations` | The passes over the ballots it took for the keep factors to settle. |
| `quota` | The quota of its last pass. |
| `tallies` | For each option still in the count, in the declared order: its votes, its keep factor and its status. |
| `exhausted` | The value no option kept: ballots whose choices ran out. |
| `surplus` | What the elected options held over the quota at the end of the round. |
| `elected` | The options elected during the round. |
| `excluded` | The option excluded at its end, or the options left out once the seats were filled. |
| `tieBreak` | How a tie for the fewest votes was broken, when there was one: `earlier_rounds` or `declared_order`. |

`Election::toArray()` writes it all as plain values, every number with its nine decimals (`4.714288797`), for a page,
a file or a comparison with a count replayed.

## The count

Every option has a keep factor: 1 while it is hopeful, 0 once excluded, and, once elected, the part of each vote
reaching it that it keeps, the rest passing to the ballot's next choice. A pass goes over the ballots: each ballot
gives every option on its list, in order, the part of its value the option keeps, until nothing is left; what is left
at the end of the list is exhausted.

- The quota is the votes kept by the options divided by one more than the seats, truncated, plus one billionth.
- A hopeful option at or above the quota is elected.
- The keep factor of each elected option is brought down to quota ÷ votes, and the pass starts again, until the surplus
  of the elected options is under one hundred-thousandth of a vote, stops shrinking, or a thousand passes have run.
- The hopeful option with the fewest votes is then excluded, and a new round begins.
- The count ends when the seats are filled, the hopeful options left being defeated, or when the hopeful options left
  are no more than the seats still empty, and are all elected.

A tie for the fewest votes is broken by the rounds before, from the latest back: the option that held the fewest in
the latest round where the tied options differ is excluded. When they never differed, the one declared last is. An
application that wants a lot declares the options of the poll in a random order.

## Exact numbers

Votes, quota, keep factors and surpluses are rational numbers of denominator one billion, held as integers by
`Billionths`; no float enters the count. Each rounding goes one way, so that no vote is ever made: the part an option
keeps of a vote is truncated, the rest passing on whole; a keep factor is rounded up. In every round, the votes held
by the options and the exhausted value add up exactly to the ballots counted.

The same ballots give the same rounds, to the last decimal, on any machine.

## Cost and bounds

A count reads the ballots once and groups identical rankings; a pass goes over the distinct rankings, not the ballots.
It runs at most one round per option and a thousand passes per round. Ten thousand ballots over ten options, in a few
hundred distinct orders, are counted in about ten milliseconds, for one seat as for three. A count holds at most a
billion ballots, so that the votes, in billionths, stay within an integer; an application lowers the bound by
registering `new MeekStv($maxBallots)` in its place, and a count beyond it throws `InvalidPoll`.

The count is fuzzed: on ballots nobody wrote, it always fills its seats when a ballot chooses anything, every round
adds up to the ballots, and nothing is thrown but the refusal of a bad poll.

## A method of the application

A method of the application that fills seats implements `Phil\PollBundle\Domain\Counting\Contract\ElectingMethod`:
`elect($rules, $ballots, $seats)` returns an `Election`. See [Extension points](extension-points.md).
