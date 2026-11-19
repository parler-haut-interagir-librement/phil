# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. It tests the model and its
refusals; each mechanism on problems built to show what sets it apart — a contested first choice, a choice lost in the
rounds but taken in serial dictatorship, the order of alternating rounds, groups, forced and forbidden pairs, closings
in cascade —; that the order of the input never changes the result; numeric identifiers; the work budget; and the
optimisation against an exhaustive search on 150 random problems, for both rank costs. In the monorepo, Infection
holds the package at 100 % of covered mutants.

## The benchmark

`tests/Benchmark/AssignmentBench.php` measures a thousand people ranking five of a hundred slots of ten seats, the
popular slots wanted far more than the others: the rounds, serial dictatorship and the optimisation, without minimums
and with a minimum of three seats, which closes slots and solves again. In the monorepo, `composer bench` runs it.

## The fuzz target

`tests/Fuzz/problem.php` feeds `ProblemReader` text nobody wrote, then runs the four assigners — rounds in both orders,
serial dictatorship, the optimisation — on every problem it reads. The reader must return a problem or throw
`InvalidProblem`; an assigner must return a result or throw `BudgetExceeded`; every result must keep the capacities,
place each applicant at most once, never in a forbidden or closed slot, and every forced applicant in its slot. Its
seeds are in `tests/Fuzz/corpus/problem`; in the monorepo, `composer fuzz -- problem` runs it.

## Testing code that assigns

Use a fixed seed, so that the result is the same at every run:

```php
$result = new RankedRounds()->assign($problem, Seed::fromString('test'));

self::assertSame('table-1', $result->placementOf('u-104')?->slot);
```

To test what happens to the applicant drawn first or last, read the order from `$result->drawOrder` rather than
guessing it from the seed.
