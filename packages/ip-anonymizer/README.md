# phil/ip-anonymizer

**IP addresses anonymized before they are kept: truncated, digested with a salt that is forgotten, or generalised.**

phil/ip-anonymizer turns a client address into what a log line, a visit counter or a rate limiter may keep in its
place. It reads IPv4 and IPv6 addresses strictly, reads an IPv4-mapped IPv6 address as its IPv4 address, and offers
three families of strategies:

- **truncation** clears the host bits past a prefix of each family — /24 and /48 by default — and still reads as an
  address;
- **keyed hashing** takes the BLAKE2b digest of the address keyed by a salt, by default a random one forgotten at the
  end of each UTC day: one address gives the same value all day, and values that nobody can link from one day to the
  next;
- **generalisation** keeps only the network in CIDR notation, the special-purpose scope of the address (`public`,
  `private`, `loopback`…), or a label from a table of networks the application writes — a region, an office —, with no
  geolocation database.

Each strategy implements `IpAnonymizer`; `Pipeline` runs address transformers before one of them — the digest of a
truncated address counts a network instead of a host — and `PerFamily` gives IPv4 and IPv6 their own.

It is a plain PHP library whose requirements are `ext-sodium` and `psr/clock`. Every class is
immutable and keeps no state between calls, so a single instance serves every request of a long-running worker.

## Status

| Part | State |
|---|---|
| Strict IPv4 and IPv6 parsing, IPv4-mapped addresses read as IPv4, RFC 5952 text on every platform | Implemented and tested |
| Networks in CIDR notation, prefix masks, `contains()` | Implemented and tested |
| Truncation by prefix of each family | Implemented and tested |
| Keyed BLAKE2b digests; random salts forgotten at the end of their period, fixed salts, salts derived from a master key | Implemented and tested |
| Generalisation to a network, a special-purpose scope, a label from a table of networks | Implemented and tested |
| `Pipeline` and `PerFamily` compositions | Implemented and tested |
| Framework integration — a log processor, a template filter, configuration | Not in this library: the adapters are a few lines, shown in [Extension points](doc/extension-points.md) |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, choosing a strategy. |
| [Addresses and networks](doc/addresses.md) | `IpAddress`, what the parser accepts and refuses, `IpNetwork`. |
| [Strategies](doc/strategies.md) | Truncation, keyed hashing, the three generalisations, and their composition. |
| [Salts](doc/salts.md) | `Salt`, `ForgottenSalt`, `FixedSalt`, `RotatingSalt`, and who can link the values of two periods. |
| [Extension points](doc/extension-points.md) | Your own strategies, salt providers and network labellers; framework adapters. |
| [Exceptions](doc/exceptions.md) | `IpAnonymizerException` and its four exceptions. |
| [Testing](doc/testing.md) | The library's tests, mutation bar, fuzz targets and benchmark. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/ip-anonymizer
```

```php
use Phil\IpAnonymizer\ForgottenSalt;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\KeyedHash;
use Phil\IpAnonymizer\Truncation;
use Symfony\Component\Clock\NativeClock;

$address = IpAddress::tryParse($request->getClientIp() ?? '');

if (null !== $address) {
    new Truncation()->anonymize($address);                 // '203.0.113.0'

    $daily = new KeyedHash(new ForgottenSalt($saltStore, new NativeClock()));
    $daily->anonymize($address);                           // '86400-20400:5f0c…', another value tomorrow
}
```

`$saltStore` is the application's `ExpiringSaltStore`, a shared cache with an expiry: see [Salts](doc/salts.md).

## Requirements

PHP 8.5 or later, `ext-sodium` and `psr/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
