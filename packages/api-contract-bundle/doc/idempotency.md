# Idempotency

A controller marked `#[Idempotent]` requires an `Idempotency-Key` header, and runs once per key: a retry gets the
first response again. The listener runs the guard of `phil/idempotency` around the controller; its keys,
fingerprints, states and stores are documented there.

```php
use Phil\ApiContractBundle\Infrastructure\Http\Idempotent;

#[Route('/api/orders', methods: ['POST'])]
#[Idempotent]                 // or #[Idempotent(ttl: 3600)], at least 1
public function create(Request $request): Response
```

| The request | The answer |
|---|---|
| No key, or a key that cannot be read | `400` problem; for an unreadable key, a fixed `detail` saying what a key is, never the exception's message |
| A new key | The controller runs; its response is kept, unless it is a `5xx` or streamed — then the key is marked failed, so that the client may retry |
| A key already answered, with the same method, path, query and body | The kept response, with `Idempotent-Replayed: true`; the controller does not run |
| A key already used with another request | `422` problem, whether the first request has finished, is running or has failed |
| A key whose first request is still running | `409` problem with `Retry-After`: the seconds left on its reservation |
| A key whose first request failed, with the same request | The controller runs again |

Every refusal is an RFC 9457 problem, `application/problem+json`. The two refusals follow the IETF draft of the header:
`422` for a key reused with another request, a mistake the client must correct, since retrying it never succeeds;
`409` for a key whose first request is still running, which the client retries after `Retry-After`.

A key is reserved for `idempotency.reservation_ttl` seconds (300 by default, or the key's own retention if
shorter) while its first request runs, then kept for its retention, `idempotency.ttl` or the attribute's `ttl`, once
answered or failed. A process that dies before its response — a fatal error, a timeout, a killed worker — leaves
nothing to complete the key, so its retries get `409` until the reservation expires, not for the whole retention.
Keep the reservation longer than the slowest request: a reservation that expires while its request still runs lets a
retry run beside it.

Only the status, the body and `idempotency.replayed_headers` are kept. A replay replaces the controller, so the listener
runs after the listeners of `kernel.controller` that read the route's controller — `DeprecationListener`, the profiler's
request collector —: a replayed response of a deprecated endpoint still announces its deprecation. A key is scoped to
its caller: `CallerScopeResolver` takes the authenticated user's identifier, `user:…`, or else the client's address,
`ip:…`. An API whose callers are clients with their own identifiers aliases `IdempotencyScopeResolver` to its own
resolver.

## The store

With DoctrineBundle, the keys are in the table `phil_idempotency` of `DbalIdempotencyStore`, on
`idempotency.connection`: scope, key, fingerprint, state, the kept response and when it expires, with a primary key
on scope and key. The bundle adds the table to the schema of that connection. A reservation is one atomic insert;
an expired key is never read. `DbalIdempotencyStore::purge()` forgets the expired keys; run it from a scheduled task.

With `idempotency.cache_pool`, the keys are in that pool instead, through `CacheIdempotencyStore`, each reservation
under a lock of `idempotency.lock_factory`. The pool and the lock store must be shared by every server that answers
the same API.

```yaml
# config/packages/phil_api_contract.yaml
phil_api_contract:
    idempotency:
        cache_pool: cache.idempotency
```

Without a store — no DoctrineBundle, no cache pool and no alias of `IdempotencyStore` —, an `#[Idempotent]`
controller fails with a `LogicException` that says so.
