# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network; the clock is a `MockClock` of
`symfony/clock`. In the monorepo, Infection holds the package at 100 % of covered mutants.

## The fuzz target

`tests/Fuzz/policy-reader.php` gives the policy reader documents nobody wrote, from the seeds of
`tests/Fuzz/corpus/policy-reader`: whatever it reads, it gives a policy or refuses with `InvalidPolicy`, never throws
anything else nor warns; a policy it gives writes a canonical form that reads back to the same form, and is decided for
a member and a visitor without throwing. In the monorepo, `composer fuzz` runs it.

## The benchmark

`tests/Benchmark/DecisionBench.php` measures the two hot paths of a listing: reading a stored policy of a dozen
conditions, and deciding it for a member holding twenty grants. In the monorepo, `composer bench` runs it.
