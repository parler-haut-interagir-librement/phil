# Changelog

All notable changes to `phil/open-badges` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Issuer`, `Achievement` and `Recipient`: the issuer's `Profile` with its web site, contact address, image and
  endorsements, and its profile document with its Ed25519 keys as Multikey verification methods; the achievement
  with its criteria, image and endorsements; the recipient as a DID or a salted SHA-256 hash of an e-mail address or
  URL, and `Recipient::identifies()` to check who presents a credential.
- `CredentialIssuer`: an `OpenBadgeCredential` of Verifiable Credentials 2.0 with its period of validity in UTC, its
  status entry and its endorsements, unsecured or secured as a JSON Web Token.
- `EndorsementIssuer`: the `EndorsementCredential` a third party signs for an achievement, a profile or a
  credential; `Endorsements`, the endorsements carried as documents or tokens.
- `Ed25519Signer`, `Ed25519Key`, `RsaSigner` and `RsaKey`: `EdDSA` through libsodium and `RS256` through OpenSSL,
  RSA keys of at least 2048 bits, Ed25519 keys read from and written as JSON Web Keys and Multikeys.
- `JwtSigner` and `JwtVerifier`: VC-JWT with the type `vc+jwt`, the key's identifier and JSON Web Key in the header,
  the registered claims mirroring the credential, the algorithm taken from the key and never from the token.
- `EddsaRdfc2022`: the Data Integrity cryptosuite `eddsa-rdfc-2022`, the proof signed over the SHA-256 of the
  canonical forms of its options and of the document; the W3C test vector reproduced.
- A JSON-LD 1.1 processor for the subset verifiable credentials use, in safe mode — a property no context defines is
  refused —, reading contexts only from `BundledContexts` (Verifiable Credentials 2.0, Open Badges 3.0.3, CLR 2.0.1)
  or what the application adds, nesting and quads bounded.
- The RDF Dataset Canonicalization RDFC-1.0, its work bounded against poison datasets; every evaluation test of the
  W3C suite passes.
- `DidKeyMethods`: the verification methods the application knows and every Ed25519 `did:key`, nothing fetched.
- `StatusList`, `StatusListEntry` and `StatusListIssuer`: revocation and suspension through a Bitstring Status List of
  at least 131,072 bits, GZIP-compressed and inflated within bounds, and its `BitstringStatusListCredential`.
- `ClrIssuer`: a Comprehensive Learner Record 2.0 embedding credentials with their proofs, and tokens as
  `EnvelopedVerifiableCredential`s.
- `PngBaker` and `SvgBaker`: a credential baked into and read from the `openbadgecredential` `iTXt` chunk of a PNG,
  every chunk and CRC checked, and the `openbadges:credential` element of an SVG, parsed without the network nor
  entities; the documentation advises sanitizing an uploaded SVG before baking it.
- `CredentialVerifier` and `VerifiedCredential`: a token or a document verified through the application's
  verification methods, the key accepted only for its own issuer — the issuer's identifier, a fragment of it, or a
  method listed for that issuer — and a token's `iss` matching the issuer, then its period of validity at a moment,
  the current time by default, and its status entry.
- `OpenBadgesException`, implemented by `InvalidBadge`, `InvalidCredential` and `InvalidImage`.
- The fuzz targets of the PNG reader, the SVG reader and the verifier, and the benchmark of verifying, signing and
  reading a baked image.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
