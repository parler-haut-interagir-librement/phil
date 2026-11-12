# Testing

| Area | What is covered |
|---|---|
| `PhilApiContractBundle` | Every service, argument, alias, tag and decoration; the defaults; no Doctrine connection without DoctrineBundle; the cache pool and its lock factory |
| `ProblemDetailsListener`, `ProblemException`, `ProblemDetailsResponse` | Problems anywhere, HTTP exceptions and others under the paths, debug, the `400` bound |
| `IdempotencyListener`, `Idempotent`, `IdempotencyReservation` | Reservation and expiry, replay, reuse, in progress with its `Retry-After`, a failed key run again, missing and bad keys, `5xx` and streams marked failed, the replayed headers, no store |
| `CallerScopeResolver` | The user, the address, neither |
| `IdempotencySchemaListener` | The table of `phil/idempotency` in the schema of its connection, and not of another; the stores themselves are tested in `phil/idempotency` |
| `DeprecatedEndpoint`, `DeprecationListener` | The headers of a deprecated controller, none on another |
| `CacheObservedDeprecations`, `DeprecationObservingHttpClient`, `DeprecationsCommand` | Once a day per endpoint, the cache, endpoints without identifiers, the path read as a browser reads it (user information, backslashes, dot segments, international hosts, IP addresses, relative URLs), one reading per response, the command's output and status |
| `VerifyCommand`, `DocumentReader`, `ContractVerifierFactory` | JSON and YAML read, unreadable documents and the size bound, the application's rules and the skipped ones, the output in text and JSON with the document's text escaped, the exit codes, the configured defaults, refused options |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite and the bundle, and sends requests to
the endpoints of `ApiController`: an order created once per key and replayed, a reused key, a missing key, a failure run
again, problems under `/api/` and not elsewhere, the deprecation headers, on a replay too; the verifier is built and
its command verifies a document in the application's console. Installed on its own, where
FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/api-contract-bundle/tests
```
