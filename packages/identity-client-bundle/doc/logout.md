# Logout

## Back-channel logout

When a session ends at the provider, it posts a logout token to `back_channel_logout.path`. Declare that URL at the
provider as the client's `backchannel_logout_uri`.

The token is verified with the pinned algorithm against the keys of the provider, then its claims with the native
`OidcLogoutToken`: issuer, audience, dates, the `events` claim, a `sid`, and no `nonce`. A token naming a user and no
session is refused rather than ending every session of that user.

Each `jti` is then accepted once. `CacheReplayStore` keeps it for `replay_ttl` seconds, or until the token can no longer
pass its `exp` check when that is later. A repeated token gets a `400` and ends nothing.

The request comes from the provider, so it cannot touch the browser's session. The ended `sid` is recorded, and the next
request of that session is deauthenticated by the native listener. `EndedSessionListener` then invalidates the whole
local session and dispatches `LocalSessionEnded`.

A provider that retries a delivery whose answer it lost gets a `400` on the second attempt, with the session already
ended.

## The session cap

A logout token can be lost. `session.max_lifetime` bounds the session whatever happens to the back channel: 12 hours by
default, counted from the moment the session started at the provider.

That moment is the `session.start_claim` of the ID token, then `auth_time`, then the sign-in itself. A date in the future
is read as the sign-in, and an elevation within the same provider session never moves it forward.

## Local logout

Logging out locally is the `logout` of the firewall. With `end_session.enabled`, the user is sent to the provider's end
session endpoint too. Use a `POST` with a CSRF token, as for any logout.
