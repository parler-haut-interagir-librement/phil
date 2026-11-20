# Logout

## Back-channel logout

When a session ends at the provider, it posts a logout token to `back_channel_logout.path`. Declare that URL at the
provider as the client's `backchannel_logout_uri`.

The token is verified with the pinned algorithm against the keys of the provider, then its claims with the native
`OidcLogoutToken`: issuer, audience, dates, the `events` claim, a `sid`, and no `nonce`. A token naming a user and no
session is refused rather than ending every session of that user.

Each `jti` is then accepted once. `CacheReplayStore` keeps it for `replay_ttl` seconds, or until the token can no longer
pass its `exp` check when that is later. A repeated token gets a `400` and ends nothing. A claim the pool did not keep —
its `save()` answers `false` — is refused the same way, so that the same token cannot be accepted twice; the provider
delivers it again later. A claim whose lock expired while the pool was read is refused too
([Lock lifetimes](token-refresh.md#lock-lifetimes)).

The request comes from the provider, so it cannot touch the browser's session. The ended `sid` is recorded, and the next
request of that session is deauthenticated by the native listener. `EndedSessionListener` then invalidates the whole
local session and dispatches `LocalSessionEnded`.

A provider that retries a delivery whose answer it lost gets a `400` on the second attempt, with the session already
ended.

## The session cap

A logout token can be lost. `session.max_lifetime` bounds the session whatever happens to the back channel: 12 hours by
default, counted from the moment the session started at the provider.

That moment is the `session.start_claim` of the ID token, `session_start` by default, then `auth_time`, then the sign-in
itself. A date in the future, or a value that is not a number, is read as the sign-in, and an elevation within the same
provider session never moves it forward.

```yaml
phil_identity_client:
    session:
        max_lifetime: 43200          # 0 or null for none
        start_claim: session_start
```

## The cold-start floor

The replay store and the ended sessions live in the `back_channel_logout.cache` pool. A pool that is flushed, evicted
or replaced forgets them: a logout token already accepted could be accepted again, and a session the provider ended
would come back. `back_channel_logout.cold_start_floor` closes that window:

```yaml
phil_identity_client:
    back_channel_logout:
        cache: cache.state           # shared by every server, and never evicting
        cold_start_floor:
            enabled: true
            not_before: ~            # seconds since the Unix epoch
```

`CacheReplayFloor` keeps one entry without expiry in that pool, its sentinel, which records the floor. When the entry
is missing, the pool was emptied: the floor becomes that instant, and the entry is written again. Then:

- a logout token whose `iat` precedes the floor is refused with a `400`, before its `jti` is looked up;
- a local session signed in before the floor is ended on its next request, and `LocalSessionEnded` reports it with
  `LocalSessionEndReason::ReplayFloor`. The user signs in again.

Each sign-in reads the floor before it is dated, so that a session begun on an emptied pool is never refused by the
floor that pool records next.

`not_before` raises the floor to an instant of its own, such as a key rollover at the provider, or the floor of the
provider's own replay store. It is in force once reached, so that it can be set ahead of time. The floor is off by
default, and needs the back-channel logout.

The pool still has to keep its entries for `replay_ttl` and never evict them: the floor tells a store that was emptied
from one that was not, not one that lost a few entries.

## Local logout

Logging out locally is the `logout` of the firewall. With `end_session.enabled`, the user is sent to the provider's end
session endpoint too. Use a `POST` with a CSRF token, as for any logout.
