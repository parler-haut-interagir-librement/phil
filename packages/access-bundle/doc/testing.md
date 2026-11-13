# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the application services on an
in-memory store and a mock clock, the voters, the controller, the configuration and the compiler passes. The functional
suite, under `tests/Functional`, boots a small application — FrameworkBundle, SecurityBundle, TwigBundle, Symfony UX
Twig Component with the recipes of the kit — and draws the banner and the summary, and starts and stops "view as this
member" through its forms. In the monorepo, Infection holds the package at 100 % of covered mutants.

The DBAL store is tested on MySQL 8.4 and PostgreSQL 17 by `phil/resource-permissions`.

## Testing an application's access

Set `phil_access.store: memory` in the test environment, and a `MockClock` as the `clock` service. Write the case with
`Items`, `Sharing`, `Spaces` and `AccessGroups`, sign a user in, and assert on `is_granted()` or on the page. Moving the
clock past an end tests access that ends on its own.
