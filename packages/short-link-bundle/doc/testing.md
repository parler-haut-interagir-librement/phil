# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the codec against the identifiers
the Sqids specification lists, the links and their terms, the classifier of visits on the `User-Agent`s of browsers,
link previews and robots, the services on a repository in memory, the listener, the adapters, and the Doctrine
repository on SQLite in memory. The functional suite, under `tests/Functional`, boots a small
application with Doctrine on SQLite, Twig and Symfony Security, and goes through the short domains and the
administration. In the monorepo, Infection holds the package at 100 % of covered mutants.

A test that needs a real server reads its URL from an environment variable and is skipped without it:
`PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`. The integration suite, under `tests/Integration`, creates the
table from the mapping on each server, reads back what is kept in UTC, checks the unique code, the count of a visit
under the link's terms, the visits of machines counted apart, and the search, and starts requests in separate
processes racing for the last visits of a link.

## The fuzz targets

`tests/Fuzz/sqid-decode.php` gives the codec identifiers nobody wrote: whatever it reads decodes to non-negative
integers or to nothing, never throws nor warns, and the numbers it decodes to come back from their own identifier.
`tests/Fuzz/visitor-classify.php` gives the classifier headers nobody wrote: it always answers a class, the same for the
same headers, and a prefetch only when a purpose names one. The monorepo runs them with `composer fuzz`, from the seeds
of `tests/Fuzz/corpus/<target>`.

## The benchmark

`tests/Benchmark/KnownVisitorClassifierBench.php` measures the classification every visit goes through: a browser, the
slowest answer, read against every pattern, takes a few microseconds; a header of 8 KiB is cut before it is read.
