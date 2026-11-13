# Testing

## The library's tests

The suite `tests/Unit` tests each part on its own: the bounds of payloads, options and definitions, the reader on every
refused shape and the writer's canonical JSON, the validator, the conditions without functions, the catalogue, the
engine on a frozen clock — runs started once per event, delays, filters, branches, retries and their backoff,
compensation in reverse order, leases held by another worker —, the webhook action on Symfony's `MockHttpClient` with
its signature verified, the Messenger handlers and the schedule, and one contract per store run against both stores,
the database ones on SQLite in memory.

From the monorepo root:

```sh
vendor/bin/phpunit packages/automations/tests
```

The suite `tests/Integration` runs the same store contracts on a real MySQL 8.4 and PostgreSQL 17, with a save that
only one of two connections wins; each server's test reads its URL from `PHIL_TEST_MYSQL_URL` or
`PHIL_TEST_POSTGRESQL_URL` and is skipped without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/automations/tests/Integration
```

`tests/Fuzz/definition-reader.php` feeds the reader JSON nobody wrote: it gives a definition that writes and reads back
to the same JSON, or refuses with `InvalidDefinition`, never anything else. In the monorepo, Infection holds the
library at 100 % of covered mutants.

## Testing an automation

Build the engine on the in-memory stores, a frozen clock and a bus that keeps its messages, then move the clock:

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-11-13 10:00:00 UTC');
$runs = new InMemoryRunStore();
$engine = new AutomationEngine($catalogue, new InMemoryDefinitions([$definition]), $runs, $bus, $clock);

[$id] = $engine->handle(new OrderPaid('42', 'customer-7'));
$engine->advance($id);              // runs up to the first delay
$clock->modify('+1 day');
$engine->advance($id);              // goes on after it

self::assertSame(RunState::Completed, $runs->get($id)?->state);
```
