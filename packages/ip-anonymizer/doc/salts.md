# Salts

A keyed digest is only as private as its salt is secret: an unkeyed digest of an IPv4 address is reversed by digesting
its 2^32 values, a few seconds of work. The salt is what makes that enumeration impossible to whoever reads the
digests without holding it.

## `Salt`

```php
use Phil\IpAnonymizer\Salt;

Salt::of('2026-q4', $secretBytes);   // 16 to 64 bytes; 32 recommended
Salt::random('ephemeral');           // 32 bytes from random_bytes()
```

The identifier is 1 to 32 characters among letters, digits, `.`, `-` and `_`. It is written at the head of each
digest, so that the digests of two salts never compare equal. `var_dump()` and other dumps show `[redacted]` in place
of the bytes.

## `ForgottenSalt`

The provider to reach for: a random salt per period of the clock, a new one each UTC day by default, which nobody can
derive again once its period is over:

```php
use Phil\IpAnonymizer\ForgottenSalt;
use Symfony\Component\Clock\NativeClock;

new ForgottenSalt($saltStore, new NativeClock());          // daily
new ForgottenSalt($saltStore, new NativeClock(), 3600);    // hourly
```

The first digest of a period draws 32 random bytes and claims them in the application's
[`ExpiringSaltStore`](extension-points.md#expiringsaltstore), for the time the period still lasts: a salt drawn at
23:59 UTC is kept for one minute. Every server reads the same salt from the store. When the entry expires, the salt is
gone: there is no key to derive it from, so the digests of two periods cannot be linked by anyone, the operator
included.

Its identifier is `<period seconds>-<index>`, `86400-20400` for 2025-11-08, counted in UTC whatever the time zone of
the clock. The salt is read from the store on each digest and is never kept in the process, so a long-running worker
holds nothing past the end of a period. Each digest therefore costs one call to the store.

## `FixedSalt`

Always the same salt: for a salt the application rotates itself — read from its secrets and changed by deployment —
and for tests. The digests of one address stay linkable for as long as the salt lives.

```php
use Phil\IpAnonymizer\FixedSalt;

new FixedSalt(Salt::of('2026-q4', $secretBytes));
```

## `RotatingSalt`

Optional, for an application where nothing may be stored and the operator may be trusted with every period. A salt per
period of the clock, derived from a 32-byte master key, a new one each UTC day by default:

```php
use Phil\IpAnonymizer\RotatingSalt;
use Symfony\Component\Clock\NativeClock;

new RotatingSalt($masterKey, new NativeClock());           // daily
new RotatingSalt($masterKey, new NativeClock(), 3600);     // hourly
```

The salt of a period is `sodium_crypto_kdf_derive_from_key()` of the master key, with the number of whole periods
since the Unix epoch as the subkey identifier; its identifier is `<period seconds>-<index>`, `86400-20400` for
2025-11-08. Every process and every server derives the same salt for the same period from the same key, with nothing
stored and nothing to synchronise. Periods are counted in UTC whatever the time zone of the clock.

Generate the master key once, keep it with the application's secrets, and never log it:

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

## Who cannot link the values

| Salt provider | Linkable across periods by |
|---|---|
| `ForgottenSalt` | nobody, once the period is over |
| `FixedSalt` | whoever holds the salt, for as long as it lives |
| `RotatingSalt` | whoever holds the master key: every past salt can be derived again |

A derived salt keeps yesterday's digests from being linked with today's by anyone who reads them — a log reader, a
backup, an analytics export — without holding the master key. The holder of that key can still link them, which is why
`ForgottenSalt` comes first.
