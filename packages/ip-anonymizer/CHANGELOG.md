# Changelog

All notable changes to `phil/ip-anonymizer` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `IpAddress` and `IpFamily`: an IPv4 or IPv6 address as its bytes, read by `parse()`, `tryParse()` and `fromBytes()`
  — the text checked before `inet_pton()`, at most 45 characters of hexadecimal digits, `:` and `.`, without a zone
  index or a dotted part with a leading zero —, an IPv4-mapped address read as its IPv4 address, and the RFC 5952
  text written the same on every platform.
- `IpNetwork`: the network of a prefix length that holds an address, CIDR notation read strictly with the host bits
  clear, `contains()` and `equals()`.
- `IpAnonymizer`, `IpTransformer`, `SaltProvider`, `ExpiringSaltStore` and `NetworkLabeller`, the contracts of the
  strategies, the address steps, the salts, the shared store of random salts and the labelled networks.
- `Truncation`: the host bits cleared past a prefix of each family, /24 and /48 by default.
- `Salt`, 16 to 64 secret bytes with an identifier, redacted from dumps; `ForgottenSalt`, a random salt per period of
  a PSR-20 clock, daily in UTC by default, claimed in an `ExpiringSaltStore` for what is left of the period, so that
  nobody can link two periods; `FixedSalt`; `RotatingSalt`, the optional salt per period derived from a 32-byte master
  key by `sodium_crypto_kdf_derive_from_key()`.
- `KeyedHash` and `IpDigest`: the BLAKE2b digest of an address keyed by the current salt, 16 to 64 bytes, written
  `<salt id>:<hex digest>`.
- `NetworkGeneralisation`: the network of an address in CIDR notation.
- `AddressScope`, `ScopeClassifier` and `ScopeGeneralisation`: the special-purpose scope of an address from the
  registries of IANA.
- `NetworkLabels` and `LabelGeneralisation`: the label of the longest prefix of a table the application writes,
  looked up in at most one hash lookup per prefix length, else a fallback strategy.
- `Pipeline` and `PerFamily`: transformers before a strategy, and a strategy per family.
- `IpAnonymizerException`, implemented by `InvalidIpAddress`, `InvalidNetwork`, `InvalidSalt` and `InvalidOption`,
  whose messages never repeat an address.
- The fuzz targets of the readers of addresses and networks, with their seeds.
- The benchmark of parsing, formatting, each strategy and a lookup in a thousand labelled networks.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
