# Testing

| Area | What is covered |
|---|---|
| `VerifiedIdentity`, `AssuranceLevels` | Kept claims, unverified addresses, the scale |
| `DeclaredOriginsReturnToPolicy` | Accepted targets and every refused form, origins an application may declare |
| `CacheReplayStore`, `EncryptedRefreshOutcomes` | Claims, lifetimes, nothing readable in the pool, tampered entries |
| `SerializedTokenRefreshListener` | The leeway, a stale request, a wait, a timeout, a revocation, an unreachable provider |
| `ReplayGuardedBackChannelLogout` | Accepted tokens, replay, signature and claim failures |
| The listeners and the voter | Each branch, the session start, an elevation that is declined |
| `ClientConfiguration`, `FirewallComposer` | Pinned defaults, refused configurations, the prepended block |

A functional suite boots a kernel with the security bundle and a local fake provider behind a mock HTTP client: discovery,
keys, and a token endpoint that rotates refresh tokens and revokes the family on reuse. It covers the sign-in, a forged
`state` or `nonce`, an unsigned token, a changed algorithm, a public key used as a secret, an embedded key, a replayed
`jti`, an external `return_to`, the cap, step-up and refresh.

```sh
vendor/bin/phpunit packages/identity-client-bundle/tests
```

`tests/Benchmark` measures the hot paths with PHPBench, `tests/Worker/worker.php` a page in the worker mode of FrankenPHP,
and `tests/Fuzz/return-to.php` feeds the `return_to` policy inputs nobody wrote.
