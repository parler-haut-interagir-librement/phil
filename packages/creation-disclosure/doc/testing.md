# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. In the monorepo, Infection holds
the package at 100 % of covered mutants.

The fuzz target `tests/Fuzz/stored-disclosure.php` feeds `CreationDisclosure::fromArray()` with what nobody wrote: it
reads a disclosure that writes back to itself, or throws `Exception\InvalidDisclosure`, and nothing else.

## Testing an application

The types are values: a test builds the disclosure it needs with `CreationDisclosure::of()` and `declare()`, or reads
it from the array a fixture stores, and compares views with `assertSame()`.
