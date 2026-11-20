# Testing

## The library's tests

The suite `tests/Unit` tests each period on exact windows — month ends, leap years, ISO weeks across years, changes of
offset, moments before the anchor and before the epoch —; one contract run against both stores — ceilings, keys per
subject and meter, refunds bounded and idempotent, totals over ranges, the purge —; the database store on SQLite in
memory, its tables, its SQL per platform and its transactions; `UsageMeter` on a frozen clock across period boundaries,
rolling buckets, anchors per subject and chained limits; and the limits read from features, through a real
`FeatureAccess` of `phil/commerce-subscription-bundle`.

From the monorepo root:

```sh
vendor/bin/phpunit packages/usage-meter/tests
```

The suite `tests/Integration` runs the same contract on a real MySQL 8.4 and PostgreSQL 17, with a refund and an
increment from two connections; each server's test reads its URL from `PHIL_TEST_MYSQL_URL` or
`PHIL_TEST_POSTGRESQL_URL` and is skipped without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/usage-meter/tests/Integration
```

The library is held to 100 % covered mutation score by Infection.

## Testing code that meters

Build the meter on an `InMemoryUsageStore` and a frozen clock, such as Symfony's `MockClock`, and move the clock to
cross a boundary:

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-03-14 10:30:00', 'UTC');
$meter = new UsageMeter($meters, new InMemoryUsageStore(), $clock);

$meter->consume('account-1', 'exports', 3);
$this->expectException(QuotaExceeded::class);
$meter->consume('account-1', 'exports');
```

```php
$clock->modify('2026-04-01 00:00:00');
self::assertSame(0, $meter->usage('account-1', 'exports')->used);
```

To test a store of your own, extend the library's contract the way `InMemoryUsageStoreTest` does, if the library's
tests are available to you, or copy its cases.
