# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | What it covers |
|---|---|
| `tests/Unit` | The configuration and its services, the defaults of the compiler pass; one contract of the stores, held on SQLite in memory; the keys made, rotated and pruned; the sealer over a local KMS; the score publisher with and without a bus, the retries of its handler, the recovery of the scores never dispatched and the refusals kept; the deep linking responses kept and resumed; two writes of one score interleaved on purpose, by a DBAL middleware; the user of a launch and its provider; the commands. |
| `tests/Functional` | An application that is a platform and a tool registered with itself: the platform starts a launch, the tool initiates the login, the platform signs the launch for its member, the tool verifies it and signs the member in; the refusals; the key set; the token endpoint; the services of the platform called with tokens of each scope; a Deep Linking response returned, and one whose listener failed, resumed without its token; the line items paged by default and at the maximum; the schema of the first version brought up to date. |
| `tests/Integration` | The same contract of the stores on MySQL 8.4 and PostgreSQL 17, with the interleaved writes of a score and the upgrade of the schema. |

The keys of the tests are generated on the first run and kept in the temporary directory, so that no private key is
committed. The integration tests read their server's URL from `PHIL_LTI_TEST_MYSQL_URL` and
`PHIL_LTI_TEST_POSTGRESQL_URL`, else from `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`, which `phil-dev services
up` and `phil-dev services env` give for the MySQL 8.4 and PostgreSQL 17 it starts in Docker; they are skipped without
it, and create their tables under the prefix `phil_lti_test_`, dropping them first.

## Testing an application

Configure the store `memory` and keys from the configuration in the test environment: the stores in memory hold the
same contract. To test a launch end to end, give the application a key set source that answers its own public keys, as
the bundle's test application does, and build the launch with `LaunchIssuer`.
