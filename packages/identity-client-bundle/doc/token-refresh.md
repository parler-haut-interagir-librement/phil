# Token refresh

`refresh.enabled` renews the access token before it expires, for an application that calls an API on behalf of the
user. Ask the provider for a refresh token, usually with the `offline_access` scope.

## The problem

A provider that rotates refresh tokens treats one presented twice as stolen and revokes the family. Two requests of the
same session, sent together when the access token expires, would do exactly that. A session handler that locks the
session prevents it, but not every handler locks, and a request that read the session before the rotation still holds
the old token.

## What the bundle does

The listener replaces the native one on the firewall. When a request finds its access token within `refresh.leeway`
seconds of expiring:

1. It takes a lock named after its refresh token, waiting up to `refresh.wait_timeout` seconds.
2. If another request already used that token, it applies the published outcome and calls nothing.
3. Otherwise it publishes a pending outcome, refreshes, and publishes the outcome before releasing the lock. When the
   pool does not keep the pending outcome — its `save()` answers `false` —, the provider is not called and the tokens
   stay as they are.

| Outcome | Effect on every request holding the token |
|---|---|
| Rotated | The new tokens are applied to the session |
| Refused by the provider (`invalid_grant`) | The whole local session is invalidated, and `RefreshTokenRejected` reports it |
| Provider unreachable | The tokens stay, and the next try comes after 5 seconds |
| Pending: the result was lost by the pool, or the request renewing died | The tokens stay, and the old refresh token is never presented again; the pending outcome lasts `refresh.outcome_ttl` seconds |

A request that times out keeps its tokens and tries again on its next request.

`TokensRefreshed` and `RefreshTokenRejected` are dispatched last, once the outcome is published, applied to the session
and the lock released. A subscriber that throws cannot lose tokens the provider has already rotated: its exception
reaches the caller, as with every other event of the bundle, but every request holding the old token still finds the
outcome.

Outcomes live in a cache pool for `refresh.outcome_ttl` seconds, encrypted with a key derived from the refresh token they
answer. The pool alone reveals nothing, and there is no key to manage.

## Settings

Keep `lock_ttl` above the timeout of the HTTP client, so that a lock cannot expire during a call to the provider. Keep
`outcome_ttl` above the longest request the application serves: a request that started before the rotation and writes its
session afterwards leaves the old token behind, and the next request must still find the outcome.

## Lock lifetimes

The refresh lock is held across the call to the provider, the one step that can last: `lock_ttl`, 30 seconds by
default, is sized above the HTTP client's timeout for that reason. Should a call outlast it anyway, the pending outcome
published before the call is what keeps the refresh token from being presented twice: the request that takes the
expired lock finds the pending outcome, leaves its tokens as they are, and does not call the provider. The pending
outcome lasts `outcome_ttl` seconds, so keep `outcome_ttl` above `lock_ttl` too: the defaults, 120 and 30, do.

The replay guard of the logout tokens holds its lock, five seconds, for one read and one write of the pool. It renews
the lock and checks it is still its own before the write; a read that outlasted the lock, or a lock store that cannot
confirm it, refuses the claim, so a logout token is refused rather than accepted twice, and the provider delivers it
again later. Give the pool a timeout well under five seconds.
