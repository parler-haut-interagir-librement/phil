# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\AccessCodes\AccessCodes` | `final readonly class` | The library in one object: `create()`, `redeem()`, `revoke()`, `batch()`, `counts()` |
| `Phil\AccessCodes\CodeAlphabet` | `final readonly class` | The symbols of a code, and the lookalikes read as them |
| `Phil\AccessCodes\CodeFormat` | `final readonly class` | Length, entropy, check symbol and grouping; draws, reads and shows codes |
| `Phil\AccessCodes\CodeHasher` | `final readonly class` | The HMAC-SHA-256 of a code under an ordered list of keys |
| `Phil\AccessCodes\Batch` | `final readonly class` | A named batch: identifier, name, size, creation, expiry, revocation |
| `Phil\AccessCodes\BatchCounts` | `final readonly class` | Total, redeemed, revoked and unused codes of a batch |
| `Phil\AccessCodes\CreatedBatch` | `final class` | A newly created batch, with its codes in clear for one use |
| `Phil\AccessCodes\RedemptionLinks` | `final readonly class` | The redemption page's address with a code filled in |
| `Phil\AccessCodes\Redemption` | `final readonly class` | The answer to a redemption |
| `Phil\AccessCodes\RedemptionOutcome` | enum | Redeemed, unknown, already redeemed, expired, revoked, too many attempts |
| `Phil\AccessCodes\CodeState` | `final readonly class` | What a store knows of one code |
| `Phil\AccessCodes\Contract\CodeStore` | interface | Where the batches and the hashes live |
| `Phil\AccessCodes\InMemoryCodeStore` | `final class` | The batches of one process |
| `Phil\AccessCodes\Dbal\DbalCodeStore` | `final readonly class` | The batches in two tables of a Doctrine DBAL connection |
| `Phil\AccessCodes\Exception\AccessCodesException` | interface | Every exception of the library |
| `Phil\AccessCodes\Exception\InvalidCode` | `final class` | A text that is not a code of the format |
| `Phil\AccessCodes\Exception\InvalidConfiguration` | `final class` | A value the library cannot work with |
| `Phil\AccessCodes\Exception\CodesAlreadyTaken` | `final class` | The codes of a created batch asked for twice |
| `Phil\AccessCodes\Exception\UnknownBatch` | `final class` | A batch the store does not hold |
