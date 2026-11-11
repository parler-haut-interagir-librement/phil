# Counting

A `CountingMethod` reads the ballots once, in their order, and returns a `Result`:

| Property | What it holds |
|---|---|
| `method` | The name of the method. |
| `ballots` | The ballots counted, abstentions included. |
| `abstentions` | The blank ballots. |
| `standings` | A `Standing` per option — `option`, `score`, `place` — from the first place down. |
| `rounds` | The rounds of an instant runoff; empty for the other methods. |

Options tied share their place, and the next place counts every option before it: two options tied first are both
first, and the next one is third. Within a place, the higher score comes first, then the declared order.
`winners()` lists the options in first place, several when they are tied, and none while no ballot has chosen
anything. `standingOf()` finds the standing of one option.

## The count of choices

`ChoiceCount`, named `choices`, counts single and multiple ballots: an option's score is the number of ballots that
chose it. For single ballots it is a plurality; for multiple ballots, an approval count. It refuses ranked ballots.

## The instant runoff

`InstantRunoff`, named `instant_runoff`, counts ranked ballots in rounds. Each ballot goes to its highest-ranked
option still in the count. An option holding more than half of the ballots still counting wins; otherwise the
option holding the fewest is eliminated and its ballots go to their next choice. A ballot that ranks no option left
is exhausted, and no longer counts towards the majority.

A tie for the fewest ballots is broken by the rounds before, from the latest back: the tied option that held the
fewest in the latest round where they differ is eliminated. When they never differed, the one declared last is
eliminated; an application that wants a lot instead declares the options in a random order when it creates the poll.
When every option still in the count is tied, the count stops and they share the first place.

The standings put the winner first, then the options still in the count by their ballots in the last round, then the
eliminated options, the last eliminated first. An option's score is the ballots it held in the last round it was in.
Each `Round` holds a `Tally` per continuing option, the exhausted ballots and the options eliminated at its end.

## The Borda count

`Borda`, named `borda`, counts ranked ballots by points: with n options, a ballot's first choice earns n − 1 points,
its second n − 2, and so on; an option a ballot does not rank earns nothing from it. The score is the total of points.

## The Schulze method

`Schulze`, named `schulze`, is a Condorcet method for ranked ballots. Every two options are compared on every ballot:
a ballot prefers an option it ranks to one it ranks lower or not at all, and prefers neither of two options it does
not rank. The strength of a path between two options is its weakest pairwise win, counted in winning votes, and an
option beats another when its strongest path to it is stronger than the path back.

An option's place counts the options that beat it: the options no other beats share the first place, and an option
that beats every other one — the Condorcet winner, when there is one — wins alone. The score is the number of options
an option beats. Two options neither of which beats the other may stand at different places, when other options beat
one of them and not the other.

## Cost

| Method | Time | Memory |
|---|---|---|
| `choices`, `borda` | one pass, proportional to the choices | the scores |
| `instant_runoff` | one pass to group, then one pass over the distinct rankings per round | the distinct rankings |
| `schulze` | one pass to group, the pairs of each distinct ranking, then the cube of the options | the distinct rankings and two square tables |

With ten options, each method counts ten thousand ballots in about ten milliseconds on a laptop; see
[Testing](testing.md#the-benchmark).
