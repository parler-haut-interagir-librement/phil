# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel or a network, on SQLite in memory. The functional suite, under
`tests/Functional`, boots a small application with the recipes of the PHIL UX kit: the page without JavaScript, the
JSON-LD checked by the `Validator` of `phil/structured-data`, the search and its count, the plain and JSON votes, the
CSRF check, the administration, the commands and the paths of the controller for AssetMapper. In the monorepo,
Infection holds the package at 100 % of covered mutants.

The tables are also tested on real servers, read from the environment and skipped without it:
`PHIL_TEST_MYSQL_URL` for MySQL 8.4 and `PHIL_TEST_POSTGRESQL_URL` for PostgreSQL 17.

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
composer test:services
```

## The Stimulus controller

```sh
node --import ./tests/js/register.mjs --test 'tests/js/**/*.test.mjs'
```

The tests run the controller and its modules under Node's test runner, on a page of plain objects, with Stimulus
resolved to a stub: the search, the count after a pause, the question of the address, the votes, the storage of the
browser refused.

## In a browser

What a person checks with a screen reader and a keyboard, once per release:

- the search field is announced with its label, and the number of matches is read after typing;
- a question opens and closes with Enter and Space, and its link opens it from another page;
- the vote buttons are announced in their group, and the thanks is read once the vote is sent;
- with JavaScript off, the search, the vote and the link to the contact form still work.
