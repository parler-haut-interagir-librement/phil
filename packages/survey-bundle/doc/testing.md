# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel or a network, on SQLite in memory: the model and its
conditions, every question type, the endings and redirects, the quotas, the anonymity of the tables, the threshold of
the results, the purge, the templates, the commands and the privacy module. The functional suite, under
`tests/Functional`, boots a small application: the page without JavaScript, its errors, the CSRF check, the ending the logic
chose, a redirect to an allowed origin, the public results and the addendum. In the monorepo, Infection holds the
package at 100 % of covered mutants.

The integration suite runs the store and the quotas on real servers, read from the environment and skipped without it:
`PHIL_TEST_MYSQL_URL` for MySQL 8.4 and `PHIL_TEST_POSTGRESQL_URL` for PostgreSQL 17. Concurrent processes answer one
questionnaire at once, and the test checks that no quota takes more than its limit.

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
composer test:services
```

## The fuzz target

`tests/Fuzz/survey-questionnaire-json.php` feeds the reader of `phil-survey/1` with mutated documents, from the seeds
of `tests/Fuzz/corpus/`; it must throw nothing but `InvalidQuestionnaire`.

```sh
composer fuzz
```

## The Stimulus controller

```sh
node --test tests/js/steps.test.mjs
```

The tests cover how the controller moves between steps, skips the hidden ones and computes the progress.
