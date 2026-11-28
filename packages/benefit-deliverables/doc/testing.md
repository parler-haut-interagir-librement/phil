# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the ports are implemented in
memory under `tests/Support`, and `LocalExportFileStore` writes to a temporary directory. In the monorepo, Infection
holds the package at 100 % of covered mutants.

## The fuzz target

`tests/Fuzz/benefit-deliverables-token.php` reads download tokens nobody signed: each input gives the identifier of
an export only when it is the token signed for it, never an exception. In the monorepo:

```sh
composer fuzz -- benefit-deliverables-token
```

Its seeds are under `tests/Fuzz/corpus/benefit-deliverables-token`.
