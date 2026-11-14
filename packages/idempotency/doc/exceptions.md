# Exceptions

Every exception implements `Exception\IdempotencyException` and keeps the SPL parent that says what kind of failure
it is.

| Exception | Parent | When | HTTP answer |
|---|---|---|---|
| `InvalidIdempotencyValue` | `InvalidArgumentException` | A key, a scope, a fingerprint, a status, a record or a time that breaks its rule. | `400` for a key read from a request |
| `MissingIdempotencyKey` | `RuntimeException` | `IdempotencyKey::fromHeader()` on an absent or blank header. | `400` |
| `IdempotencyKeyReused` | `RuntimeException` | The key was first used with another payload. | `422` |
| `RequestInProgress` | `RuntimeException` | The first run is still in progress; `$retryAfter` holds the seconds left on its reservation, at least 1. | `409` with `Retry-After` |
| `ReservationLost` | `RuntimeException` | The run ended after its reservation expired and another run reserved the key, or after the key was released: its outcome was not kept. | `500`; lengthen the reservation past the slowest run |

Answer them as RFC 9457 problem details. Do not echo the message of `InvalidIdempotencyValue` for a key read from a
request: say what a key is instead.

Next: [extension points](extension-points.md).
