# Changelog

All notable changes to `phil/identity-provider-google-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.0-alpha.

### Added

- The configuration `phil_identity_provider_google`: the client ID and secret, the accepted Google Workspace domains,
  the profile, the prompt, the clock skew, the lifetime of a sign-in, the cache pool, the HTTP client, the clock, and
  the receiver of security events — its audiences, the age of a token, the holds and their pool.
- `GoogleProvider`, the `ExternalProvider` of phil/identity-provider-bundle for Google, and `GoogleSignIn`: the
  authorization code flow on Symfony Security's discovery, token client, signature verifier and claim checks, with a
  state, a nonce and a PKCE challenge (S256) kept in the session by `SignInAttempts` — five at most, each taken once —,
  the `iss` parameter checked, the scopes `openid` and `email` only, no refresh token and no token kept; every refusal
  `external.failed`, its cause logged without a token or the person's data.
- `GoogleClaims`: the subject, the address handed over as verified only when Google is its authority — a `gmail.com`
  address, or a Workspace account on its own domain —, the accepted domains checked on `hd`, and the name only with
  `profile`.
- `SafeHttpTransport`, `SafeHttpResponse` and `ReadChunk`: Symfony's HTTP client contract over phil/safe-http, with
  no redirect, 1 MiB and 10 seconds at most per answer, and only the options Symfony's OpenID Connect pieces use.
- The receiver of Google's Cross-Account Protection and of the Shared Signals Framework: `SecurityEventToken` and its
  events, read within bounds; `SecurityEventTokenReader`, which checks the signature against the transmitter's keys,
  its issuer, audience and age; `SecurityEventReceiver`, which closes the sessions of the linked account through the
  bundle's `Sessions`, holds a Google account disabled as hijacked until it is enabled again, acknowledges a token
  received before through `ReplayGuard`, and dispatches `SecurityEventReceived`; `TransmitterSettings`, with
  `google()`; `SubjectHolds` and `CacheSubjectHolds`.
- `RiscController` and its route `phil_identity_provider_google_risc`: `202` once acted on, `400` with the error code
  of RFC 8935, `503` when the keys cannot be read, `404` while the receiver is off.
- The Flex recipe: the bundle, its configuration and its route, and `GOOGLE_CLIENT_ID`.
- The fuzz target `google-claims`.
- The documentation in `doc/`: installation, configuration, linking accounts, Cross-Account Protection, extension
  points, testing and reference.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

