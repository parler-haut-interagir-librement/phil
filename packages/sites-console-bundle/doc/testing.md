# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | What it covers |
|---|---|
| `tests/Unit` | The keys and the signed messages, the documents of the protocol and their limits, versions and patterns, the models, the alert rules, the agent's verifier and endpoints with `phil/idempotency` on an array pool, the console keys an agent accepts and their store, the key rotation of the console — stop at the first failure, resumption, sites that left —, the console's services with a stand-in agent client, the HTTP client against the agent's controller through a mock transport — the whole signed exchange both ways —, the lock file reader, the health from a stand-in of `phil/monitoring-bundle`, the memory stores and the Doctrine stores on SQLite, the configuration and its passes. |
| `tests/Functional` | A kernel that is both the console and the site "shop", with SecurityBundle, sessions, CSRF, Messenger, TwigBundle, Twig Component and the recipes of the kit, phil/monitoring-bundle, DoctrineBundle on SQLite and a fixed clock, its HTTP client handing each request back to its own agent: the screens with their roles and tokens, every command, a key rotation stopped and resumed, the handlers, and the Doctrine mapping. |
| `tests/Integration` | The Doctrine stores again on MySQL 8.4 with `PHIL_TEST_MYSQL_URL` and on PostgreSQL 17 with `PHIL_TEST_POSTGRESQL_URL`; skipped without them. |

Nothing else needs a network or a server. In the monorepo, `composer test:services` starts the servers and runs the
integration suite, and Infection holds the package at 100 % of covered mutants.

## Fuzzing

Three readers of bytes nobody wrote declare fuzz targets, run from the seeds of `tests/Fuzz/corpus/<target>`:

| Target | What it reads |
|---|---|
| `status-document` | The status an agent answers: it reads, or is refused with `MalformedDocument`, and reads the same once written back. |
| `composer-lock` | A site's `composer.lock`: only checked names and versions come out, sorted, and nothing is thrown. |
| `key-request` | What the console posts to `keys`, and the console keys an agent stores: each reads, or is refused with `MalformedDocument`, and reads the same once written back. |

```sh
composer fuzz -- status-document --budget=60
```

## Testing an application

Give the test kernel a `MockClock` as `clock`, and to the agent a cache pool on that clock — `ArrayAdapter` takes one —
so that the nonces and the idempotency keys expire by it. The console's `http_client` can be a `MockHttpClient` that
hands each request to the kernel, as the functional test of the bundle does, to play the whole exchange in one
process. `store: memory` keeps the console's rows in memory.
