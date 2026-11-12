# Testing

## The library's tests

The suite `tests/Unit` tests what the parser accepts and refuses — leading zeros, zone indexes, brackets, NUL bytes,
the longest text —, IPv4-mapped addresses read as IPv4, the RFC 5952 text of IPv6 against random addresses read back
by `inet_pton()`, the masks of every partial byte, CIDR notation, truncation, salts and their bounds, the daily and
custom periods of random and derived salts and their UTC boundaries, the shared store of a forgotten salt and what is
left of its period, keyed digests against `sodium_crypto_generichash()`, every special-purpose range of the scopes,
the longest-prefix lookup of labelled networks, and the compositions.

From the monorepo root:

```sh
vendor/bin/phpunit packages/ip-anonymizer/tests
```

The library is held to 100 % covered mutation score by Infection.

## The fuzz targets

`tests/Fuzz/ip-address.php` and `tests/Fuzz/ip-network.php` feed the two readers text nobody wrote: an address must
read back from its own text to the same bytes, a network to the same network, and nothing but `InvalidNetwork` may be
thrown. `composer fuzz ip-address ip-network`, from the monorepo root, runs them from the seeds of `tests/Fuzz/corpus`.

## The benchmark

`tests/Benchmark/IpAnonymizerBench.php` measures what one anonymized request costs: parsing an IPv4 and an IPv6
address, writing an IPv6 text, each strategy, a digest under a rotating salt and under a forgotten one, and a lookup in
a table of a thousand labelled networks. `composer bench`, from the monorepo root, runs it with the other benchmarks.

## Testing code that anonymizes

Give `KeyedHash` a `FixedSalt` for values that do not change between runs, and `RotatingSalt` a frozen clock, such as
Symfony's `MockClock`, to test a change of period:

```php
use Phil\IpAnonymizer\KeyedHash;
use Phil\IpAnonymizer\RotatingSalt;
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-03-14 23:59:59 UTC');
$hash = new KeyedHash(new RotatingSalt(str_repeat('k', 32), $clock));
$before = $hash->anonymize($address);

$clock->sleep(1);
self::assertNotSame($before, $hash->anonymize($address));
```
