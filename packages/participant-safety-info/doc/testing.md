# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the roles and the windows are
implemented under `tests/Support`, the store and the journal by the package's in-memory implementations, and the
sealer runs over the `InMemoryKms` of Symfony's KeyManagement component, which refuses a wrong key, context or client
as a real KMS does. In the monorepo, Infection holds the package at 100 % of covered mutants.

## The fuzz target

`tests/Fuzz/participant-safety-info-codec.php` decodes plaintexts nobody encoded: each input decodes to information
that encodes the same twice, or is refused as unreadable, never another exception. In the monorepo:

```sh
composer fuzz -- participant-safety-info-codec
```

Its seeds are under `tests/Fuzz/corpus/participant-safety-info-codec`.
