# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: the store's promises are one abstract test case,
`TupleStoreTestCase`, run on the in-memory store and on the DBAL store over SQLite in memory. In the monorepo,
Infection holds the package at 100 % of covered mutants.

The integration suite, under `tests/Integration`, runs the same promises on real servers, with the byte-by-byte
collation, the longest identifiers and a listing over a long list. It reads `PHIL_TEST_MYSQL_URL` and
`PHIL_TEST_POSTGRESQL_URL`, and is skipped without them.

## Fuzzing and benchmarks

`tests/Fuzz/relation-checker.php` builds graphs from the fuzzer's bytes — nested and cyclic groups, folders under
themselves, every member, exclusions — and holds that every check answers or refuses with `TraversalBudgetExceeded`,
that an explanation exists exactly when the check says yes, and that a listing holds only what checks, and all of it
when it says it is complete. `composer fuzz -- relation-checker` runs it in the monorepo.

`tests/Benchmark/RelationCheckerBench.php` measures a check and a listing on chains of 4 to 16 folders.

## Testing code that checks access

Build the application's model with an `InMemoryTupleStore` on a `MockClock`, write the tuples of the case with
`RelationWriter`, and check. Moving the clock past an expiry tests access that ends on its own.
