# Reference

All types live under `Phil\Idempotency`.

| Type | Kind | Summary |
|---|---|---|
| `IdempotencyKey` | final readonly class | The key of one intention: `HEADER`, `MAX_LENGTH`, `generate()`, `fromString()`, `fromHeader()`, `toHeaderValue()`, `equals()`. |
| `IdempotencyScope` | final readonly class | Whose keys these are: `MAX_LENGTH`, `of()`, `equals()`. |
| `PayloadFingerprint` | final readonly class | The SHA-256 of an operation: `$hex`, `ofRequest()`, `ofParts()`, `fromHex()`, `equals()`. |
| `StoredResponse` | final readonly class | The answer kept for a replay: `$status`, `$headers`, `$body`, `of()`, `nothing()`. |
| `IdempotencyState` | string enum | `InProgress` (`in_progress`), `Completed` (`completed`), `Failed` (`failed`). |
| `IdempotencyRecord` | final readonly class | What a store holds: `$fingerprint`, `$state`, `$expiresAt`, `$response`; `inProgress()`, `completed()`, `failed()`, `of()`, `secondsLeft()`. |
| `IdempotencyOutcome` | enum | `Proceed`, `Replay`, `Reused`, `InProgress`. |
| `IdempotencyDecision` | final readonly class | `for()`, `$outcome`, `$existing`, `replay()`. |
| `IdempotencyGuard` | final readonly class | `begin()`, `complete()`, `fail()`, `run()`, `inProgress()`. |
| `IdempotentRun` | final readonly class | What `run()` returns: `$response`, `$replayed`. |
| `Contract\IdempotencyStore` | interface | `find()`, `reserve()`, `complete()`, `fail()`, `release()`. |
| `Store\DbalIdempotencyStore` | final readonly class | The store on a Doctrine DBAL connection; `purge()`, `reservation()`. |
| `Store\IdempotencyTable` | final class | `NAME`, `configure()`. |
| `Store\CacheIdempotencyStore` | final readonly class | The store on a PSR-6 pool under a Symfony lock. |
| `Messenger\IdempotencyMiddleware` | final readonly class | Handles a received message once per key. |
| `Messenger\IdempotencyStamp` | final readonly class | `DEFAULT_SCOPE`, `$key`, `$scope`. |
| `Exception\IdempotencyException` | interface | What the library throws. |
| `Exception\InvalidIdempotencyValue` | final class | A value that breaks its rule; `because()`. |
| `Exception\MissingIdempotencyKey` | final class | No key on a request that needs one; `onRequest()`. |
| `Exception\IdempotencyKeyReused` | final class | The key was used with another payload; `withAnotherRequest()`. |
| `Exception\RequestInProgress` | final class | The first run is still going; `$retryAfter`, `retryAfter()`. |
