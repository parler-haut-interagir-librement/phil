# Changelog

All notable changes to `phil/api-contract-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `ProblemDetailsListener`, `ProblemException`, `ProblemDetailsResponse`.
- `Idempotent`, `IdempotencyListener`, `IdempotencyReservation`, `IdempotencyScopeResolver`, `CallerScopeResolver`.
  `#[Idempotent(ttl: …)]` below one second is refused, and an unreadable `Idempotency-Key` is answered with a fixed
  `detail`, `IdempotencyListener::KEY_FORMAT`.
- `IdempotencyListener` runs the guard of `phil/idempotency`: a key whose first request is still running is a `409`
  problem with `Retry-After`, the seconds left on its reservation; a key used with another request a `422` problem,
  as the IETF draft of the `Idempotency-Key` header answers;
  a `5xx` or a streamed response marks the key failed, so that the same request runs again. A key is reserved for
  `idempotency.reservation_ttl` seconds, then kept for its retention once answered, so a process that dies before its
  response blocks retries of its key for the reservation only.
- `IdempotencySchemaListener`, which adds the table of the DBAL store of `phil/idempotency` to the schema of its
  connection.
- `DeprecatedEndpoint`, which builds its signal when constructed, and the compiler pass `CheckDeprecatedEndpoints`,
  which builds every one of the controller services when the container is compiled, so an unreadable one fails the
  build; `DeprecationListener`, which adds the deprecation `Link` beside the response's own `Link` headers.
- `DeprecationObservingHttpClient`, which replaces by `{id}` every path segment that does not read as a name (a
  letter, then letters, digits, `-` and `_`, at most two digits and 32 characters); its documentation says that a
  value spelt like a word still needs `extra.endpoint`. The path is read by PHP's WHATWG URL parser, as a browser
  reads it: backslashes, dot segments, user information and relative URLs included.
- `CacheObservedDeprecations`, which keeps one cache item per endpoint and an index of their names, writes an
  endpoint only when its daily warning is due or its signal changed, and keeps it eight days from that write:
  concurrent requests keep each other's observations, and an endpoint not called for a week stops failing
  `phil:api-contract:deprecations`. `DeprecationsCommand`.
- `VerifyCommand`, `phil:api-contract:verify`: an OpenAPI document, JSON or YAML, verified at a level of rules of
  `phil/api-contract`, its findings printed escaped as text or JSON, exit code `1` when one reaches `--fail-on` and `2`
  when the document cannot be read; `DocumentReader`, which bounds the file before reading it; the public service
  `Phil\ApiContract\OpenApi\ContractVerifier`, built by `ContractVerifierFactory` with the application's own rules,
  tagged `phil_api_contract.openapi_rule` by autoconfiguration.
- `PhilApiContractBundle`, extension alias `phil_api_contract`, keys `problem_details.paths`, `idempotency.ttl`,
  `idempotency.reservation_ttl` (300 by default), `idempotency.replayed_headers`, `idempotency.connection`, and
  `idempotency.cache_pool` with `idempotency.lock_factory` for keys kept in a cache pool, and `verification.document`,
  `verification.level`, `verification.fail_on` and `verification.skip`. The contract's types are
  `phil/api-contract`, under `Phil\ApiContract`, and the idempotency's `phil/idempotency`, under `Phil\Idempotency`,
  which the bundle requires. The clock is required: `DeprecationObservingHttpClient`, `DeprecationsCommand` and
  `IdempotencyListener`
  take a `ClockInterface` before their optional arguments, and the bundle wires the container's `clock` service,
  without falling back to the system's time.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
