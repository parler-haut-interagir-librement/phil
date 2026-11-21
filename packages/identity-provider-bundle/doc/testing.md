# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the domain — encodings, TOTP
against the vectors of RFC 6238, PKCE against those of RFC 7636, keys and JSON Web Signatures, the CBOR reader and the
WebAuthn checks against a software authenticator —, the application services on memory stores, the stores on SQLite,
the adapters and the configuration. In the monorepo, Infection holds the package at 100 % of covered mutants.

The functional tests, under `tests/Functional`, boot a small application with every module on and play the flows in a
browser: registration and verification, the lock, the forgotten password, the second step, step-up, passkeys, sessions
closed from another device, sign-in links, phone numbers, a federated provider, the commands, and the whole OpenID
Connect flow with discovery, consent, tokens, userinfo, introspection, revocation and logout.

A test that needs a real server reads its URL from an environment variable and is skipped without it: the stores run
again on MySQL 8.4 with `PHIL_TEST_MYSQL_URL` and on PostgreSQL 17 with `PHIL_TEST_POSTGRESQL_URL`
(`tests/Integration`).

## Benchmarks

`tests/Benchmark/JwsBench.php` signs an ID token and verifies an access token with each algorithm, the work of every
token request and every userinfo call:

```sh
vendor-bin/phpbench/vendor/bin/phpbench run packages/identity-provider-bundle/tests/Benchmark --report=phil
```

## The OpenID conformance suite

`tests/Conformance/run.sh` plays the OpenID Foundation's conformance suite against the provider in Docker; see
[OpenID conformance](openid-conformance.md).

## Fuzzing

The readers of what browsers, authenticators and clients send declare fuzz targets in `phil-dev.php`, run by
`composer fuzz` in the monorepo from the seeds of `tests/Fuzz/corpus/<target>`:

| Target | What it reads |
|---|---|
| `cbor` | CBOR items and COSE keys |
| `authenticator-data` | The authenticator data of WebAuthn |
| `client-data` | The client data of WebAuthn |
| `jwt` | JSON Web Tokens, against one known key |
| `authorization-request` | The query of an authorization request |
| `attestation` | The attestation statements of new passkeys, `none` and `packed` |
