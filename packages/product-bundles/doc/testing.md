# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. In the monorepo, Infection holds
the package at 100 % of covered mutants.

`tests/Support/Shop` implements the three component ports with arrays: prices and costs in Canadian cents, units in
stock.

## The functional flow

`tests/Functional/BundleFlowTest` takes a starter set from its definition to its return — price, stock, cost and
margin read, a sale frozen and its stock drawn, the line stored as JSON and read back, a whole bundle returned — then a
cooperative bundle of three creators approved, stored, read back and its first sale shared out.

## Fuzzing

`tests/Fuzz/product-bundles-stored.php` reads every input as a stored bundle, a sold line and a cooperative bundle in
JSON. Each gives a value or `InvalidBundle`, never another exception, and a row read back writes the same array:

```sh
composer fuzz -- product-bundles-stored
```

## The benchmarks

`tests/Benchmark/BundleBench` measures a bundle of 50 components quoted, its stock and margin read, a sale frozen, and
its stored line read back:

```sh
composer bench -- packages/product-bundles/tests/Benchmark
```
