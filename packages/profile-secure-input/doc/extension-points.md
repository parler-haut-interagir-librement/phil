# Extension points

The profile has no code, so it has no extension points of its own: an application changes the behaviour in the
members, each through its configuration, its ports and its events.

| To | See |
|---|---|
| Change how addresses are read, typed and stored | The documentation of `phil/url-bundle` |
| Change what an outgoing request may reach | The `Limits` and `AddressGuard` of `phil/safe-http` |
| Declare, key or answer rate limits | The configuration `phil_rate_limit` of `phil/rate-limit-bundle` |
| Tune the spam checks of a form | The option `spam_protection` of `phil/spam-protection-bundle` |
| Scan with another antivirus, or quarantine elsewhere | The ports of `phil/upload-scan` and the configuration of `phil/upload-scan-bundle` |
| Leave out a member | Require the others directly: see [Upgrading](upgrading.md) |

The members are at `0.x`: their extension points are stable only from their 1.0 releases.
