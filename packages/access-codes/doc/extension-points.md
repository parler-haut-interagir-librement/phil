# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

The library is plain PHP and declares no service. `AccessCodes` takes a store, a hasher, a rate limiter factory, a
clock, and optionally a format and a randomizer.

## `CodeStore`

`Phil\AccessCodes\Contract\CodeStore` — where the batches and the hashes live. `InMemoryCodeStore` and
`Dbal\DbalCodeStore` are shipped; see [Keys and storage](storage.md).

| Method | Contract |
|---|---|
| `add(Batch $batch, array $hashes): void` | Adds the batch and one hash per code, all or none; refuses a batch identifier or a hash stored already with `InvalidConfiguration` |
| `redeem(array $candidates, string $redeemer, DateTimeImmutable $now): ?string` | Marks the code redeemed only if it is neither redeemed, revoked nor expired at `$now`, atomically; returns its batch, or `null` |
| `find(array $candidates): ?CodeState` | What the store knows of the code, or `null` |
| `batch(string $batchId): ?Batch` | The batch, or `null` |
| `revoke(string $batchId, DateTimeImmutable $now): int` | Marks the batch and its unredeemed codes revoked; returns how many codes, 0 for a batch revoked before or unknown |
| `counts(string $batchId): BatchCounts` | Total, redeemed and revoked codes |

A store of the application's own receives hashes, never codes, and keeps the promise of `redeem()`: of two calls at
the same moment for one code, one wins.

## The other arguments

| Argument | What to pass |
|---|---|
| `CodeHasher` | The ordered keys, read from the secrets vault |
| `RateLimiterFactoryInterface` | Any Symfony rate limiter factory, on a store shared by the servers |
| `Psr\Clock\ClockInterface` | The application's clock; a `MockClock` in tests |
| `CodeFormat` | Another alphabet, length, check symbol or grouping |
| `Random\Randomizer` | The `Secure` engine by default; a seeded engine in tests only |
