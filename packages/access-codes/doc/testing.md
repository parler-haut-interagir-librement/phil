# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite `tests/Unit` tests the alphabets and their lookalikes, the formats and their bounds, check symbols pinned
by hand, the keyed hashes pinned and their ordered keys, the batches, the one-time CSV and list, the prefilled links,
`AccessCodes` on a frozen clock and an in-memory rate limiter, and one contract run against both stores, the DBAL one
on SQLite in memory. In the monorepo, Infection holds the package at 100 % of covered mutants.

The suite `tests/Integration` runs the same contract on a real MySQL 8.4 and PostgreSQL 17, a redemption from
another connection, and eight processes racing on one code; each server's test reads its URL from
`PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` and is skipped without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/access-codes/tests/Integration
```

## Fuzzing and benchmark

The fuzz target `code-format` feeds the code reader arbitrary text: whatever it reads must have the format's length
and alphabet and read back to itself, shown and typed in lowercase, and it throws nothing but `InvalidCode`.

```sh
composer fuzz -- code-format --budget=60
```

The benchmark `BatchGenerationBench` creates a batch of 100,000 codes on an in-memory store — about 0.5 s and 43 MB on
a recent laptop — and reads and hashes one typed code under two keys, about 9 µs.

```sh
composer bench
```

## Testing code that redeems

Build `AccessCodes` on an `InMemoryCodeStore`, a `MockClock` and a rate limiter on Symfony's `InMemoryStorage`, take
the codes from the created batch, and move the clock past an expiry:

```php
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

$clock = new MockClock('2026-11-13 10:00:00');
$codes = new AccessCodes(
    new InMemoryCodeStore(),
    new CodeHasher(str_repeat('k', 32)),
    new RateLimiterFactory(['id' => 'test', 'policy' => 'no_limit'], new InMemoryStorage()),
    $clock,
);

[$code] = $codes->create('Test', 1, $clock->now()->modify('+1 hour'))->takeCodes();
$clock->sleep(3600);
self::assertSame(RedemptionOutcome::Expired, $codes->redeem($code, 'member-1', 'test')->outcome);
```
