# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the responsible people are
implemented under `tests/Support`, the stores by the package's in-memory implementations, and the sealer runs over the
`InMemoryKms` of Symfony's KeyManagement component, which refuses a wrong key, context or client as a real KMS does.
In the monorepo, Infection holds the package at 100 % of covered mutants.
