# Extension points

## `Contract\IdempotencyStore`

A store of your own — another database, a key-value service — implements five methods:

| Method | Contract |
|---|---|
| `find($scope, $key): ?IdempotencyRecord` | Never returns an expired record. |
| `reserve($scope, $key, $fingerprint, $expiresAt): bool` | **Atomic**: `false` when an unexpired record is in progress or completed; an expired or failed record is replaced. |
| `complete($scope, $key, $response, $expiresAt): void` | Attaches the response and keeps the key until the retention, whatever expiry the reservation had. |
| `fail($scope, $key, $expiresAt): void` | Marks the key failed until the retention, and drops any response. |
| `release($scope, $key): void` | Forgets the key, whatever its state. |

Completing, failing or releasing an unknown key does nothing. Keep the fingerprint, never the payload.

The library's contract test runs against any store: extend `Phil\Idempotency\Tests\Unit\Store\IdempotencyStoreTestCase`
from the package's tests and return your store from `createStore(MockClock $clock)`.

## The fingerprint

`PayloadFingerprint::ofParts()` takes the parts you choose. For an operation whose payload carries a field that
changes on each attempt, leave that field out; for one whose meaning is in a few fields, fingerprint those.

## The guard

`IdempotencyGuard` is final. To change how an operation is answered, wrap it: the guard's methods are the steps,
and `begin()` returns the decision for you to act on.
