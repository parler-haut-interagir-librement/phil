# Changelog

All notable changes to `phil/open-badges-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-14

### Added

- The configuration `phil_open_badges`: the issuer's profile, checked when the container is built; its key, an
  Ed25519 seed or an RSA key in PEM read from a secret when first used; how its credentials are secured; the trusted
  keys of other issuers; the status lists; the verification page; the reading of other issuers' profiles. Registered
  without its issuer and key, the bundle stops the build and names the options missing; the issuer's addresses are
  read by `phil/url`, `localhost` refused unless `allow_localhost` is true.
- The services of `phil/open-badges`, configured: the issuer, its signer, `CredentialIssuer`, `EndorsementIssuer`,
  `ClrIssuer`, `StatusListIssuer`, `JwtSigner`, `EddsaRdfc2022` with the JSON-LD contexts the application provides
  as files (`contexts`), the verification methods and `CredentialVerifier`.
- `Securing`: the issuer's credentials secured as JSON Web Tokens or with `eddsa-rdfc-2022` proofs, as configured,
  with the issuer's verification method `<issuer id>#key-1`, each returned as a `SecuredCredential` that carries its
  content and its media type.
- `StatusLists`, the port of the application's status lists, and `StatusListContent`; `StatusListUrls`, the entry
  a revocable credential carries and the list an entry points to, its path read by `phil/url`, `localhost` refused unless
  `allow_localhost` is true, and the whole URL compared with the route's.
- The routes of the issuer's profile with its Multikey, of its JSON Web Key Set, and of each status list as a secured
  `BitstringStatusListCredential`, all cacheable and open to other origins.
- The verification page: a credential pasted or a baked PNG or SVG image, checked for its proof, its period, its status
  in the application's lists and, when given, its recipient's identity; a `422` when it does not hold; its layout
  configurable; in English and French; its checks counted against a limiter of `phil/rate-limit-bundle` named by
  `verification.rate_limit`, a limiter that bundle does not declare stopping the build.
- `RemoteVerificationMethods`, enabled by `remote_issuers`: the keys of issuers the application does not configure,
  read through `phil/safe-http` — public HTTPS hosts only, redirects checked, time and bytes bounded — from the
  profile at their HTTPS identifier or, for a `did:web`, from the DID document at the address `DidWeb` of
  `phil/open-badges` gives, the document holding
  only when its `id` is that identifier; the keys kept those of its `verificationMethod` it controls and asserts, at
  most 20: Ed25519 as `Multikey` or `JsonWebKey`, RSA as `JsonWebKey` read by `RsaKey::fromJwk()`, a key with a private member left aside, so that
  `EdDSA` and `RS256` tokens and `eddsa-rdfc-2022` documents of other issuers verify; the keys, or the refusal,
  cached for a bounded time in the application's pool; an optional list of trusted issuers, HTTPS or `did:web`; a
  clear refusal when the document cannot be read; the checks of the verification page still counted by its limiter.
- The documentation in `doc/`, one file per topic, indexed by the README, with where 1EdTech conformance testing
  stands and how to verify a credential today.
- The fuzz target `issuer-profile`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
