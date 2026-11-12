# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Readonly classes, typed constants, `new` without parentheses. |
| `ext-sodium` | bundled with PHP | BLAKE2b keyed digests, the key derivation of rotating salts, constant-time hexadecimal. |
| `psr/clock` | `^1.0` | The salts of a period read it from a PSR-20 clock. |

## Installing

```sh
composer require phil/ip-anonymizer
```

## Choosing a strategy

| You need | Strategy | Example of what is kept |
|---|---|---|
| A value that still reads as an address, for tools made for addresses | `Truncation` | `203.0.113.0`, `2001:db8:1234::` |
| To count visitors or rate-limit clients without keeping their address | `KeyedHash` with `ForgottenSalt` | `86400-20400:5f0c9b…` |
| To show how much of the address was kept | `NetworkGeneralisation` | `203.0.113.0/24` |
| Only whether the client is local, private or public | `ScopeGeneralisation` | `public` |
| A region or a site of your own, without a geolocation database | `LabelGeneralisation` over `NetworkLabels` | `office` |

The strategies compose: see [Strategies](strategies.md#composing-strategies).

## Building them once

Every class is immutable and keeps no state between calls. Build the strategies once — as services of a container,
for instance — and share them across the requests of a long-running worker. `NetworkLabels` and `ScopeClassifier`
build their lookup tables in their constructor, so building them once also saves that work.
