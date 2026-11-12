# Security

## What the bundle guarantees

| Threat | Protection | Where |
|---|---|---|
| Forged or re-signed ID token, `alg: none`, public key used as a shared secret | One pinned asymmetric algorithm, keys only from the provider's key set | `oidc_login`, `signature_algorithm` |
| Keys embedded in a token header (`jwk`, `jku`, `x5u`) | Never read | `oidc_login` |
| Replayed or foreign authorization response | `state`, `nonce` and PKCE | `oidc_login` |
| A firewall block that turns PKCE or the ID token signature check off | Build refused | `NativeAuthenticatorPass` |
| Session fixation | The session identifier changes at sign-in; `session_fixation_strategy: none` refused at build | `FirewallComposer` |
| Account takeover through an e-mail address | Identity keyed on `sub`, unverified addresses dropped | `VerifiedIdentity` |
| Open redirect after sign-in | `return_to` read with the browser's parser, and rebuilt | `DeclaredOriginsReturnToPolicy` |
| Forged logout token ending sessions | Signature and claims checked | `OidcBackChannelLogout` rules |
| Replayed logout token | Each `jti` once | `ReplayGuardedBackChannelLogout` |
| Logout token that never arrives | Session cap | `SessionCapListener` |
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

The `_failure_path` and `_target_path` parameters of the Symfony handlers stay readable on the callback. Symfony only
redirects to them on the host of the request or within the session cookie domain, and a successful sign-in needs the
`state` of the browser's own session, so they cannot lead a user to another site.

A logout token is told from an ID token by its `events` claim and the absence of `nonce`, as OpenID Connect
Back-Channel Logout 1.0 requires; its `typ` header is not checked, since providers do not all send `logout+jwt`.

Report a vulnerability as described in [SECURITY.md](../SECURITY.md).
