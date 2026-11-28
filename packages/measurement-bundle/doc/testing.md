# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

- `tests/Unit` runs without a kernel, a database or a network: the bundle's services and configuration, the form type
  and its transformer, the validators, the Twig filters and the column type on the SQLite platform. In the monorepo,
  Infection holds the bundle at 100 % of covered mutants.
- `tests/Functional` boots a kernel in `fr_CA` with FrameworkBundle, TwigBundle and DoctrineBundle on SQLite in
  memory: a weight entered in a form on the unit of a channel, validated with French messages, written by Twig, and
  stored in its column with the application's own unit, then read back.
- `tests/Integration` writes measurements to a table created from the column type and reads them back: on SQLite in
  memory, and on MySQL and PostgreSQL when `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL` give their URLs, the
  tests being skipped without them.
