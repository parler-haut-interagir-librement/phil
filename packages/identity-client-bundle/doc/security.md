# Security

## What the bundle guarantees

| Threat | Protection | Where |
|---|---|---|
| Forged or re-signed ID token, `alg: none`, public key used as a shared secret | One pinned asymmetric algorithm, keys only from the provider's key set | `oidc_login`, `signature_algorithm` |
| Keys embedded in a token header (`jwk`, `jku`, `x5u`) | Never read | `oidc_login` |
| Replayed or foreign authorization response | `state`, `nonce` and PKCE | `oidc_login` |
| Mix-up: a code of one provider sent to another | The `iss` of the authorization response (RFC 9207) checked against the issuer of the discovery document, and required when the provider announces `authorization_response_iss_parameter_supported` | `oidc_login` |
| A firewall block that turns PKCE or the ID token signature check off | Build refused | `NativeAuthenticatorPass` |
| Session fixation | The session identifier changes at sign-in; `session_fixation_strategy: none` refused at build | `FirewallComposer` |
| Account takeover through an e-mail address | Identity keyed on `sub`, unverified addresses dropped | `VerifiedIdentity` |
| Open redirect after sign-in | `return_to` read with the browser's parser, and rebuilt | `DeclaredOriginsReturnToPolicy` |
| Forged logout token ending sessions | Signature and claims checked | `OidcBackChannelLogout` rules |
| Replayed logout token | Each `jti` once | `ReplayGuardedBackChannelLogout` |
| Logout token that never arrives | Session cap | `SessionCapListener` |
| Replay store or ended sessions lost with an emptied pool | Logout tokens and sessions older than the cold-start floor refused, when turned on | `CacheReplayFloor`, `SessionRequirementsListener` |
| A provider answering with a weaker `acr` than asked for | The returned `acr` checked against the required level, sessions below it ended | `SignInListener`, `SessionRequirementsListener` |
| Refresh token family revoked by a race | One refresh per token, shared outcome | `SerializedTokenRefreshListener` |
| Refresh token family revoked by the provider | The whole local session invalidated | `SerializedTokenRefreshListener` |
| Tokens leaked from a session or a cache | Kept only when read; shared results encrypted | `SignInListener`, `EncryptedRefreshOutcomes` |

## What stays with the application

- Serving over HTTPS, with `Secure` session cookies.
- Sharing the lock store and the cache pools between servers; see [Installation](installation.md).
- Trusting the right hosts, since local redirects are built from the request.
- Rate-limiting the back-channel logout endpoint at the edge. Each post costs one signature check at most, and the key set
  is cached.
- A `POST` with a CSRF token for logout.

## Limits

The bundle relies on service identifiers of the security bundle for `oidc_login`, which version `8.2` introduced; a
later minor version can move them. `NativeAuthenticatorPass` then stops the build and names the missing service, so
that the change is found when the application is built rather than at a sign-in.

The `iss` of the authorization response is checked by the `oidc_login` authenticator of Symfony 8.2 itself, before the
`error` parameter and before the code is exchanged: an `iss` other than the issuer of the discovery document, compared
byte for byte, is refused, and so is a response without it from a provider whose discovery document announces
`authorization_response_iss_parameter_supported`; a provider that does not announce it may leave it out. The bundle
adds no check of its own, and its functional tests prove that behaviour through the firewall it composes, so that a
change of the authenticator fails them.

The `_failure_path` and `_target_path` parameters of the Symfony handlers stay readable on the callback. Symfony only
redirects to them on the host of the request or within the session cookie domain, and a successful sign-in needs the
`state` of the browser's own session, so they cannot lead a user to another site.

A logout token is told from an ID token by its `events` claim and the absence of `nonce`, as OpenID Connect
Back-Channel Logout 1.0 requires; its `typ` header is not checked, since providers do not all send `logout+jwt`.

Report a vulnerability as described in [SECURITY.md](../SECURITY.md).
