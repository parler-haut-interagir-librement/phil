# Changelog

All notable changes to `phil/identity-client-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
