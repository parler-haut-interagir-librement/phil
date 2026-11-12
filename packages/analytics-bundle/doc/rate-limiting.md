# Rate limiting and the client digest

The collection endpoint is public and runs without credentials, so a rate limiter **per site and per keyed client
digest** bounds it. The digest is the only trace of the client address the limiter keeps.

| Piece | State |
|---|---|
| `ClientDigester`, `ClientDigest` | Implemented and tested |
| `Phil\IpAnonymizer\Contract\SaltProvider` (the salt of the period) | From `phil/ip-anonymizer`; `ForgottenSalt` by default |
| `CollectionRateLimiter` (the limiter) | Contract; default adapter `SymfonyCollectionRateLimiter` |
| `CacheSaltStore` (where the salt of the day is shared) | Implemented and tested, the default `ExpiringSaltStore` |

## `ClientDigester`

`Phil\AnalyticsBundle\Domain\Collection\ClientDigester` composes `phil/ip-anonymizer`: a `Pipeline` that runs
`Truncation(32, 64)`, then `KeyedHash` under the salt provider it is given.

```php
use Phil\IpAnonymizer\ForgottenSalt;

$digester = new ClientDigester(new ForgottenSalt($saltStore, $clock));
$digest = $digester->digestOf('192.0.2.1');
$digest->toString();  // '86400-20400:<32 hex characters>' — the form a rate limiter keys on
$digest->equals($digester->digestOf('::ffff:192.0.2.1')); // true
```

| Input | What is digested |
|---|---|
| IPv4 `192.0.2.1` | The four bytes of the address |
| IPv4-mapped IPv6 `::ffff:192.0.2.1` | The IPv4 address it maps |
| Any other IPv6 address | **Its /64 prefix**, the rest cleared |
| Anything else (empty, a host name, `1.2.3.4.5`) | Throws `InvalidValue` |

Why the /64: a single IPv6 host rotates freely inside its /64, so a per-address budget would not bound it. Every
address of a /64 is therefore one digest. `::1` and the deprecated IPv4-compatible form `::192.0.2.1` are treated as
IPv6.

**The salt.** `ForgottenSalt` draws a random salt per UTC day and shares it between servers through an
`ExpiringSaltStore`, which forgets it at the end of the day: nobody can link the digests of two days, the operator
included. Its salts live in the same kind of store as the limiter's state. `RotatingSalt`, derived from a master key,
stores nothing, and its holder can link every period. A new salt starts a new set of digests and the identifier of the
salt is part of the text, so two salts never share a limiter entry: the end of a period starts the limiter's
counts afresh.

## `ClientDigest`

`Phil\AnalyticsBundle\Domain\ValueObject\ClientDigest` is the text of the digest, in its own type: a limiter cannot be
given a bare address by mistake, and `fromString()` refuses anything that is not `<salt id>:<hexadecimal digest>`.

The digest is not a secret: it is a key the limiter stores in clear, and the salt never passes through it. `equals()`
compares in constant time anyway (`hash_equals()`).

## The contract

```php
interface CollectionRateLimiter
{
    /** Whether one more send from this digest is within the site's budget. */
    public function consume(SiteId $site, ClientDigest $digest): bool;
}
```

Invariants of a `CollectionRateLimiter` adapter:

- **It fails closed.** When its backend cannot answer, `consume()` returns `false` (or throws): a page view lost
  is better than a counter without a bound.
- Its state holds the digest and **never the address**, with a time to live no longer than the budget's window,
  in a store that does not persist it (no append-only file, no snapshot).
- **Calibrate the budget on a group behind a single address** (a conference Wi-Fi, a carrier-grade NAT), not on a
  single visitor. A page view is followed by a second send when the page is left, and by one send per event: count
  several sends per page.
- It is consumed **only after** the site's status is checked, so a suspended or retired site spends no budget and
  leaves no digest ([collection-and-admission.md](collection-and-admission.md)).

## The default adapter

`SymfonyCollectionRateLimiter` takes a limiter from a Symfony Rate Limiter factory for each site and digest, keyed on
the hash of the two, and consumes one send. The bundle configures the factory as a sliding window of
`rate_limiter.limit` sends per `rate_limiter.interval` (600 per 10 minutes by default), its state in
`rate_limiter.cache_pool` ([configuration.md](configuration.md#the-rate-limiter)). Any exception of the limiter or of its
storage answers `false`: the page view is dropped.

The digest's salt comes from `ForgottenSalt` over `CacheSaltStore`: a random salt per UTC day in `salt.cache_pool`,
claimed under a lock when a lock factory is given, and forgotten by the pool at the end of the day
([configuration.md](configuration.md#the-salt)).

Another adapter, written by the application, is in [extension-points.md](extension-points.md).
