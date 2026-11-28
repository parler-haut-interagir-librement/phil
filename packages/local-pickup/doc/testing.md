# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. In the monorepo, Infection holds
the package at 100 % of covered mutants.

`tests/Support` holds a store open every day from 10:00 to 18:00 in Montréal, two orders a slot, and a recorder that
implements every port and fails a notice when told to.

## The functional flow

`tests/Functional/PickupFlowTest` takes the whole path: the slots offered, two orders filling one slot, both made ready,
the worker sending the notices, a manual arrival, the hand-over of a scanned code to a designated collector at a booth's
register, and the other order put back in stock when its hold period ends.

## Fuzzing

`tests/Fuzz/local-pickup-stored.php` reads every input as a scanned payload and as a stored pickup row in JSON. Each
gives a value or `InvalidPickup`, never another exception, and a row read back writes the same array:

```sh
composer fuzz -- local-pickup-stored
```

## The benchmarks

`tests/Benchmark/PickupBench` measures the slots of a week while 400 orders are booked, the code checked at the
counter, and a stored pickup read back:

```sh
composer bench -- packages/local-pickup/tests/Benchmark
```
