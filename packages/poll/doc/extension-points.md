# Extension points

The library has one port, `Phil\Poll\Contract\CountingMethod`. An application adds a way of counting by implementing
it, and declares it beside the library's in `CountingMethods`.

## `CountingMethod`

```php
interface CountingMethod
{
    public function name(): string;

    public function supports(BallotKind $kind): bool;

    /**
     * @param iterable<Ballot> $ballots
     */
    public function count(PollRules $rules, iterable $ballots): Result;
}
```

- `name()` is what a poll stores to be counted by the method: 1 to 64 lowercase ASCII letters, digits or `_`, unique
  among the methods of the application.
- `supports()` says which kinds of ballot the method counts; `count()` throws `InvalidPoll` for the others.
- `count()` reads the ballots once — they may come from a generator over a table — and keeps no state between two
  counts, so that one instance serves every request of a long-running worker. It throws `InvalidBallot` for a choice
  that is not one of the rules' options.

A count of first preferences, for ranked ballots shown as a plurality:

```php
use Phil\Poll\BallotKind;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Result;
use Phil\Poll\Standing;

final readonly class FirstPreferences implements CountingMethod
{
    public function name(): string
    {
        return 'first_preferences';
    }

    public function supports(BallotKind $kind): bool
    {
        return BallotKind::Ranked === $kind;
    }

    public function count(PollRules $rules, iterable $ballots): Result
    {
        if (!$this->supports($rules->kind)) {
            throw InvalidPoll::because('First preferences are counted on ranked ballots.');
        }

        $scores = array_fill_keys($rules->options, 0);
        $ballotCount = 0;
        $abstentions = 0;

        foreach ($ballots as $ballot) {
            ++$ballotCount;

            if ($ballot->isAbstention()) {
                ++$abstentions;

                continue;
            }

            $first = $ballot->choices[0];
            $rules->has($first) || throw InvalidBallot::because(sprintf('"%s" is not an option.', $first));
            ++$scores[$first];
        }

        arsort($scores);
        $standings = [];
        $place = 0;
        $previous = null;

        foreach ($scores as $option => $score) {
            $place = $score === $previous ? $place : \count($standings) + 1;
            $previous = $score;
            $standings[] = new Standing((string) $option, $score, $place);
        }

        return new Result($this->name(), $ballotCount, $abstentions, $standings);
    }
}
```

```php
use Phil\Poll\CountingMethods;
use Phil\Poll\Counting\Borda;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\Counting\Schulze;

$methods = new CountingMethods([new ChoiceCount(), new InstantRunoff(), new Borda(), new Schulze(), new FirstPreferences()]);
```

`phil/poll-bundle` declares every service implementing `CountingMethod` beside the library's methods.
