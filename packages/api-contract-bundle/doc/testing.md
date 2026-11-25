# Testing

| Area | What is covered |
|---|---|
| `PhilApiContractBundle` | Every service, argument, alias, tag and decoration; the defaults; no Doctrine connection without DoctrineBundle; the cache pool and its lock factory |
| `ProblemDetailsListener`, `ProblemException`, `ProblemDetailsResponse` | Problems anywhere, HTTP exceptions, write conflicts and others under the paths, debug, the `400` bound |
| `IdempotencyListener`, `Idempotent`, `IdempotencyReservation` | Reservation and expiry, replay with the original's attributes and no arguments, a new key that leaves the controller and its arguments, reuse, in progress with its `Retry-After`, a failed key run again, missing and bad keys, `5xx` and streams marked failed, a reservation lost to another request (`409`, or the `5xx` left as it is), the replayed headers, no store |
| `CallerScopeResolver`, `ActiveTenantResolver`, `TokenAttributeClientResolver`, `AnonymousCallers` | The tenant, the client and the user hashed together; two tenants of `phil/tenancy-bundle`, two clients, a tenant and a client of one name kept apart; a client without a user; anonymous callers refused, or scoped by address within their tenant; the token attribute, absent, empty, not a string or not read |
| `IdempotencySchemaListener` | The table of `phil/idempotency` in the schema of its connection, and not of another; its SQL on MySQL, MariaDB, PostgreSQL and SQLite, `utf8mb4_bin` on MySQL and MariaDB; on a real MySQL and PostgreSQL, scopes and keys that differ by case or accent kept as distinct rows. The stores themselves are tested in `phil/idempotency` |
| `DeprecatedEndpoint`, `DeprecationListener` | The headers of a deprecated controller, none on another |
| `CacheObservedDeprecations`, `DeprecationObservingHttpClient`, `DeprecationsCommand` | Once a day per endpoint, the cache, endpoints without identifiers, the path read as a browser reads it (user information, backslashes, dot segments, international hosts, IP addresses, relative URLs), one reading per response, the command's output and status |
| `VerifyCommand`, `DocumentReader`, `ContractVerifierFactory` | JSON and YAML read, unreadable documents and the size bound, the application's rules and the skipped ones, the output in text and JSON with the document's text escaped, the exit codes, the configured defaults, refused options |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, SecurityBundle with two users and
the bundle, one tenant per host, and sends requests to the endpoints of `ApiController`: an order created once per key
and replayed, a reused key, a missing key, a failure run again, two tenants and two users on one store with the same
key, a permission revoked during the retention refused by `#[IsGranted]` without the stored body, a refused request
that reserves no key, an anonymous caller refused, problems under `/api/` and not elsewhere, the deprecation headers,
on a replay too; the verifier is built and
its command verifies a document in the application's console. Installed on its own, where
FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/api-contract-bundle/tests
```

The Integration suite runs against the MySQL and PostgreSQL of `phil-dev services`, read from `PHIL_TEST_MYSQL_URL` and
`PHIL_TEST_POSTGRESQL_URL`, and is skipped without them:

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/api-contract-bundle/tests/Integration
```
