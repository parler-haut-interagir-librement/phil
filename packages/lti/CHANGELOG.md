# Changelog

All notable changes to `phil/lti` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency (`symfony/cache-contracts` 3.8, `symfony/service-contracts` 3.8).

## 0.2.0-alpha - 2026-11-17

`ScoreStore::record()` answers whether it kept the score, `AgsService::lineItems()`
answers a page, and `DeepLinkingResult` gains its key and its claims.

### Added

- `DeepLinkingResult::$key`, the name of a deep linking response for good — a SHA-256 of its client and of its nonce —,
  `DeepLinkingResult::key()`, which gives it, `DeepLinkingResult::$claims`, the verified claims of the response, and
  `DeepLinkingResult::fromClaims()`, which reads them again without the token, so that an application that kept them
  handles a response again after a failure.

### Changed

- `ScoreStore::record()` compares the moment of the score with the one kept and writes in one atomic step, and answers
  whether it kept the score; `AgsService::score()` answers its result instead of reading the latest score first, so
  that of two scores of one learner written at once the more recent stays. `InMemoryScoreStore` keeps the more recent
  score; a store of the application's own implements the new contract.
- `AgsService::lineItems()` answers a page — `lineItems`, `limit` and `nextOffset` — as `NrpsService::page()` does:
  the store is asked for one line item more than the limit, 100 by default and 1,000 at most, so that the next page is
  known at every size, also without a limit and beyond 1,000.
- `DeepLinkingReturn::read()` builds its result by `DeepLinkingResult::fromClaims()`; the constructor of
  `DeepLinkingResult` takes the key first and the claims last.

## 0.1.0-alpha - 2026-11-15

### Added

- `Jwt`, `JwtSigner` and `JwtVerifier`: compact tokens read with bounded size and depth before any other reader sees
  them, critical header parameters and unencoded payloads refused; signed and verified through `web-token/jwt-library`
  with `RS256` and the key identifier, by `RS256` only with the key their `kid` names, the key set read again once when
  it lacks it; `exp`, `iat` and `nbf` checked by a PSR-20 clock with a leeway and a longest age.
- `RsaPublicKey`, `RsaPrivateKey`, `KeySet`, the port `KeyStore` and `InMemoryKeyStore`: JSON Web Keys of
  `web-token/jwt-library`, RSA of 2048 to 8192 bits with an odd exponent of at least 3, read from PEM, encrypted or not,
  or from JSON Web Keys, written to key sets and to a private JSON Web Key or its JSON for a store that seals them, read back by `fromJson()`,
  named by their RFC 7638 thumbprint when they have no identifier, generated for a rotation; retired public keys
  published beside the current one. A dump of a private key shows its identifier and its public half only.
- The port `KeySetSource`, `SafeHttpKeySetSource` over `phil/safe-http` and `CachedKeySetSource` over Symfony's cache
  contracts, whose refresh is bounded to once a minute per address.
- The messages of LTI 1.3: `LaunchMessage` read from claims and written back, with `MessageType`, `Roles` and the
  vocabularies `ContextRole`, `InstitutionRole` and `SystemRole`, `Person`, `Context`, `ResourceLink`, `ToolPlatform`,
  `LaunchPresentation`, the custom claims, `AgsEndpoint`, `NrpsEndpoint` and `DeepLinkingSettings`; `Launch` with its
  issuer, client and every claim; `Claim`, the names of the claims.
- The tool: `LoginRequest` and `LoginInitiator`, which answers a third-party initiated login with an authentication
  request, a `state` and a `nonce` remembered for the launch, and a target on the tool's origin; `LaunchValidator`,
  which verifies the launch; `DeepLinkingResponder`, which signs the content items picked.
- The platform: `LaunchStarter`, `AuthenticationRequest` and `LaunchIssuer`, which start a launch and sign its
  `id_token` for the person signed in; `DeepLinkingReturn` and `DeepLinkingResult`; the token endpoint
  `TokenEndpoint`, `AccessToken`, `IssuedToken`, the port `AccessTokens` and `ServiceGuard`; `AgsService` with the ports
  `LineItemStore` and `ScoreStore`; `NrpsService` with the port `Roster`.
- Deep Linking 2.0: `ResourceLinkItem`, `LinkItem`, `FileItem`, `HtmlItem` and `ImageItem`, with `Image` and
  `LineItemRequest`, read by `ContentItems`.
- LTI Advantage for the tool: `AccessTokenProvider` (client credentials by JWT assertion, RFC 7523, cached), `AgsClient`
  with `LineItem`, `Score`, `Result`, `ActivityProgress` and `GradingProgress`, `NrpsClient` with `Member`,
  `MemberStatus` and `MembershipPage`; `Scope`, the scopes.
- The port `ServiceClient` and `SafeHttpServiceClient`, which sends every authenticated call — the token endpoint,
  Assignment and Grade Services, Names and Role Provisioning Services — through `SafeHttpClient::send()` of
  `phil/safe-http`: HTTPS only, private networks refused, no redirect by default, the headers kept on the origin of the
  address, the answer bounded to 4 MiB by default; `LinkHeader`, which reads the `Link` header of RFC 8288.
- The ports `PlatformRegistrations`, `ToolRegistrations`, `LoginStates` and `Nonces`, and adapters in memory of every
  port.
- Fuzz targets for the tokens and key sets, the claims of a launch and the content items, and the `Link` header.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
