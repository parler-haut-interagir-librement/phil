# Changelog

All notable changes to `phil/api-contract-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.3.2-alpha.

### Added

- Under `problem_details.paths`, an `OptimisticLockException` of Doctrine, thrown or wrapped up to eight levels deep,
  is answered with a `409` problem of the stable type `urn:phil:problem:write-conflict`
  (`ProblemDetailsListener::WRITE_CONFLICT_TYPE`), in debug too, without the entity nor its versions; it was a `500`.
  `doctrine/orm` joins `suggest`.
### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `symfony/http-client-contracts` 3.8).

### Fixed

- The compiler pass that adds the tenant scope of the idempotency keys to the middleware of each bus runs before the
  one of `symfony/messenger`, which Symfony 8.2.0-RC1 registers in its own bundle at the default priority, so the
  buses keep that middleware.

## 0.3.1-alpha - 2026-11-18

Anonymous callers can be accepted per endpoint, and the keys of Messenger messages can
be scoped to their tenant.

### Added

- `Idempotent::$anonymous`: `#[Idempotent(anonymous: 'ip')]`, or `'refuse'`, or a case of `AnonymousCallers`,
  replaces `idempotency.anonymous` for one endpoint; another string is refused. The listener hands it to the scope
  resolver in the request attribute `IdempotencyListener::ANONYMOUS_CALLERS`.
- `Infrastructure\Messenger\TenantIdempotencyScopeMiddleware` and `TenantScopedIdempotencyStamp`, and the option
  `idempotency.messenger.tenant_scope`, false by default and refused without `PhilTenancyBundle`: the tenant of a
  received message's `TenantStamp` in the scope of its `IdempotencyStamp`, so that two tenants sending the same key
  are both handled and a replay once. `symfony/messenger` is suggested.
- `idempotency.messenger.buses` and the compiler pass `AddTenantIdempotencyScopeMiddleware`: with
  `tenant_scope`, the bundle tags `TenantIdempotencyScopeMiddleware` `messenger.middleware` for every bus whose
  middleware holds the `IdempotencyMiddleware` of `phil/idempotency`, or for the buses named, before that middleware
  and after the `TenantMiddleware` of `phil/tenancy-bundle`; a bus that lists it already keeps it once, where its
  configuration puts it, and a bus named that does not exist fails the build.
- The documentation of anonymous callers per endpoint and their trade-offs, and of the keys of messages per tenant.

## 0.3.0-alpha - 2026-11-17

Idempotency keys are scoped by tenant, API client and user, anonymous callers are
refused, and a replay runs the controller's access checks first. See
[Upgrading from 0.2](doc/idempotency.md#upgrading-from-02).

### Added

- `IdempotencyTenantResolver`, the tenant a request acts in, and `ActiveTenantResolver`, its default, on the active
  tenant of `phil/tenancy-bundle` when it is installed.
- `IdempotencyClientResolver`, the API client that sent a request, and `TokenAttributeClientResolver`, its default, on
  an attribute of the security token.
- `AnonymousCallers` and the options `idempotency.anonymous` (`refuse` by default, or `ip`) and
  `idempotency.client_attribute` (`client_id` by default).
- `CallerScopeResolver::ANONYMOUS` and `IdempotencyListener::PRIORITY`.
- The documentation of the access checks before a replay, of the scope of a key in a multi-tenant application, of the
  threat model, and of the upgrade from 0.2.

### Changed

- `CallerScopeResolver` scopes a key by the verified tenant, the API client and the user, each when there is one, as
  `caller:` and the SHA-256 of these parts, instead of `user:…` or `ip:…`: two tenants or two clients sharing a store
  never share a key, and the table holds no user identifier. Its constructor takes the token storage, the two ports and
  `AnonymousCallers`.
- A caller that is neither a user nor a client is answered with a `403` problem, unless `idempotency.anonymous: ip`
  scopes it by its address; before, every anonymous caller behind one address shared its keys without a word.
- `IdempotencyListener::onController(ControllerEvent)` is now `onControllerArguments(ControllerArgumentsEvent)`, on
  `kernel.controller_arguments` after the listeners of the controller's attributes. A request refused by an access
  check no longer reserves its key.
- The bundle requires `phil/idempotency` `^0.3@dev`.

### Security

- A replay no longer skips the access checks of the controller: the replayed response took the controller's place
  with no attributes, so that `#[IsGranted]` and its kin never ran, and a caller who lost a permission while the key
  was kept could read the stored body again. The access checks of the controller and of its arguments now run before
  every replay, and the replay keeps the controller's attributes.
- The keys of two tenants sharing a store are kept apart: the same user, key, path and body in another tenant was
  answered with the first tenant's response.
- `IdempotencySchemaListener` builds the table for the platform of its connection: on MySQL and MariaDB, `scope` and
  `idempotency_key` take `utf8mb4_bin`, where they had the connection's default collation, which folds case and
  accents, so that two scopes or keys differing only by case named one row.

## 0.2.0-alpha - 2026-11-14

### Fixed

- A request whose controller outlived its idempotency reservation, after which another request reserved the key,
  no longer overwrites that request's outcome: before, its late answer or failure replaced the other request's
  record, so that a retry could run a third time or replay the wrong response.

### Changed

- A request that ends after another request took its key answers a `409` problem,
  `IdempotencyListener::RESERVATION_LOST`, instead of keeping its response; a `5xx` of its own is left as it is.
- `IdempotencyReservation` holds the `Phil\Idempotency\IdempotencyReservation` the store gave, and the retention,
  instead of the scope and the key.
- The bundle requires `phil/idempotency` `^0.2@dev`, whose stores write an outcome only under the token of its
  reservation; a table created with 0.1 needs its `token` column.

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
