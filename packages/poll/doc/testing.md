# Testing

## The library's tests

The suite `tests/Unit` tests the rules — every bound, the alphabet and the length of an option, the options declared
twice — and the checks of a ballot, with their messages; the count of choices, single and multiple; the instant
runoff, with its majority, its transfers, its exhausted ballots, its ties broken by the latest round where the tied
options differ and then by the declared order, and its tie of every option; the Borda count; the Schulze method on
the example of its author, on a cycle, on partial rankings and on two options neither of which beats the other; the
places shared by tied options; and the registry of methods.

From the monorepo root:

```sh
vendor/bin/phpunit packages/poll/tests
```

The library is held to 100 % covered mutation score by Infection.

## The benchmark

`tests/Benchmark/CountBench.php` counts ten thousand ballots over ten options by each method — single ballots for the
count of choices, ranked ballots three to ten choices deep for the others — and measures the check of one ballot.
`composer bench`, from the monorepo root, runs it with the other benchmarks.

## Testing a count

Build the rules, check the ballots with them, and compare the standings:

```php
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\PollRules;

$rules = PollRules::ranked(['a', 'b', 'c']);
$result = new InstantRunoff()->count($rules, [
    $rules->ballot(['a', 'b']),
    $rules->ballot(['b']),
    $rules->ballot(['c', 'b']),
]);

self::assertSame(['b'], $result->winners());
self::assertSame(['c'], $result->rounds[0]->eliminated);
```
