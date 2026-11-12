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
3. Otherwise it refreshes and publishes the outcome before releasing the lock.

| Outcome | Effect on every request holding the token |
|---|---|
| Rotated | The new tokens are applied to the session |
| Refused by the provider (`invalid_grant`) | The whole local session is invalidated, and `RefreshTokenRejected` reports it |
| Provider unreachable | The tokens stay, and the next try comes after 5 seconds |

A request that times out keeps its tokens and tries again on its next request.

Outcomes live in a cache pool for `refresh.outcome_ttl` seconds, encrypted with a key derived from the refresh token they
answer. The pool alone reveals nothing, and there is no key to manage.

## Settings

Keep `lock_ttl` above the timeout of the HTTP client, so that a lock cannot expire during a call to the provider. Keep
`outcome_ttl` above the longest request the application serves: a request that started before the rotation and writes its
session afterwards leaves the old token behind, and the next request must still find the outcome.
