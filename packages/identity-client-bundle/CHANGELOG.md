# Changelog

All notable changes to `phil/identity-client-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-18

The next version is 0.2.1-alpha: the replay guard renews its lock before it writes a claim. See
[Token refresh](doc/token-refresh.md#lock-lifetimes).

### Security

- `CacheReplayStore::claim()` renews its lock and checks it is still its own before it writes the claim, and refuses
  the claim otherwise: a pool read that outlasted the lock's five seconds let another request take the lock and claim
  the same identifier, so that one logout token could be accepted twice. A lock that cannot be released is left to
  expire.

## 0.2.0-alpha - 2026-11-17

The next version is 0.2.0-alpha: a logout token whose claim the pool did not keep is refused, and
`RefreshOutcomes::publish()` returns whether the store kept the outcome.

### Added

- `RefreshStatus::Pending`: a request is renewing the tokens, or did and could not share the result; the tokens stay as
  they are, and the refresh token is never presented again.

### Changed

- `RefreshOutcomes::publish()` returns a `bool`: a store of the application's own answers whether it kept the outcome.

### Security

- `CacheReplayStore::claim()` refuses a claim the pool did not keep, its `save()` answering `false`: the logout token
  was accepted without its `jti` being remembered, so that it could be replayed until it expired.
- `SerializedTokenRefreshListener` publishes a pending outcome before it calls the provider, and does not call it when
  the pool does not keep that outcome; when the result is then lost, the pending outcome keeps the requests holding the
  previous refresh token from presenting it again. A rotation whose result the pool did not keep let those requests
  present the rotated token, which a provider detecting reuse answers by revoking the whole family.

## 0.1.1-alpha - 2026-11-14

### Fixed

- `SerializedTokenRefreshListener` dispatches `TokensRefreshed` and `RefreshTokenRejected` once the outcome is
  published, applied to the session and the lock released. A subscriber that threw used to leave the rotated tokens
  unpublished, so that a request still holding the previous refresh token presented it again to a provider that had
  already rotated it; a subscriber throwing an `AuthenticationException` also turned a rotation into an unreachable
  provider. The exception of a subscriber still reaches the caller, after the fact.

## 0.1.0-alpha - 2026-11-12

### Added

- `PhilIdentityClientBundle`, extension alias `phil_identity_client`, which composes the `oidc_login` authenticator of
  a declared firewall: issuer, client id, one of a client secret, a private key or a public client, scopes, kept
  claims, one pinned signature algorithm, the `sub` claim as the identifier, PKCE with `S256`; a session fixation
  strategy of `none` refused at build.
- `NativeAuthenticatorPass`, which stops the build when a native service of `oidc_login` the bundle builds on is
  missing, or when the authenticator of the firewall no longer uses PKCE with `S256` or no longer checks the signature
  of the ID token.
- `UserProvisioner`, the port that turns a `VerifiedIdentity` into the user of the application, with
  `StatelessUserProvisioner` as its default and `IdentityUserProvider` as the user provider of the firewall. An
  `email` or a `phone_number` the provider does not state as verified is left out of the identity.
- The tokens the features turned on have no use for are dropped from the session.
- `ReturnToPolicy` and `DeclaredOriginsReturnToPolicy`: a local `return_to` read with the WHATWG parser, kept in the
  session, never sent to the provider, and discarded in favour of the landing page when refused.
- `ReplayGuardedBackChannelLogout`, on the native endpoint, with the `ReplayStore` port and `CacheReplayStore`: each
  `jti` accepted once, for 24 hours by default; a refused token logged without its detail.
- `EndedSessionListener`, which invalidates the local session of an ended provider session, and `SessionCapListener`,
  with a cap of 12 hours by default measured from the start of the session at the provider.
- `AssuranceLevels`, `AssuranceVoter` granting `OIDC_ASSURANCE:<level>`, `acr_values` on the authorization request,
  `ElevationAttempts` against a provider that declines, and `AssuranceChallengeListener` answering a script with the
  challenge of RFC 9470.
- `SerializedTokenRefreshListener`, in place of the native refresh listener, with the `RefreshOutcomes` port and
  `EncryptedRefreshOutcomes`: one refresh at a time per refresh token, the other requests applying its result; a
  revoked family invalidates the whole local session.
- The events `UserSignedIn`, `ProviderSessionEnded`, `LocalSessionEnded`, `ElevationRequested`, `ElevationRefused`,
  `TokensRefreshed`, `RefreshTokenRejected` and `ReturnToDiscarded`, which carry identifiers only.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
