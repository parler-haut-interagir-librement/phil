# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the store in memory passes the
contract tests of `src/Test`, and the relay runs on a frozen clock of `symfony/clock`. In the monorepo, Infection holds
the package at 100 % of covered mutants.

## In an application's tests

`InMemoryOutboxStore` and a frozen clock make a relay that a test drives batch by batch:

```php
$store = new InMemoryOutboxStore();
$clock = new MockClock('2026-11-17 10:00:00');
$relay = new Relay($store, $codecs, $dispatcher, $clock);

$service->complete($holder, $set);
$relay->relay();

$clock->sleep(60); // past the first delay of a failed attempt
$relay->relay();
```

## The fuzz target

`tests/Fuzz/payload.php` gives the reader of the stored payloads bytes nobody wrote: each is read as a payload within
its bounds or refused as one, and a payload read is written back to the same payload. `composer fuzz` runs it from the
seeds of `tests/Fuzz/corpus/payload`.

## The benchmark

`tests/Benchmark/RelayBench.php` measures recording a hundred events, then relaying them in one batch on the store in
memory: `composer bench`.
