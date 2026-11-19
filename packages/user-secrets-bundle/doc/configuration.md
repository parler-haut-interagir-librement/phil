# Configuration

```yaml
# config/packages/phil_user_secrets.yaml
phil_user_secrets:
    current_key: k2026         # required: a master key of the KMS
    kms: null                  # the component's default client, or a service such as key_management.vault
    store: doctrine            # or "memory", for tests
    entity_manager: default
    refresh_leeway: 60
    lock_factory: null         # FrameworkBundle's lock.factory when defined; false for none; or a service
    refresh_lock_ttl: 30
    log_events: false
```

| Option | Default | Meaning |
|---|---|---|
| `current_key` | — | The master key of the KMS that seals every new value — a key of a `sodium://` DSN, an alias, an ARN —, written beside each value it seals. |
| `kms` | `null` | The service of a client of Symfony's KeyManagement component — any `DataKeyGeneratorInterface`, such as `key_management.<client>` — that seals the values; `null` for the component's default client. [Encryption and key rotation](encryption.md) explains both, and the local `sodium://` client. |
| `store` | `doctrine` | Where secrets are kept: Doctrine, or a PHP array that lasts one process — for tests. |
| `entity_manager` | `default` | The Doctrine entity manager of the table. |
| `refresh_leeway` | `60` | How many seconds before its expiry an OAuth token is already refreshed, so that it does not expire during the call it is read for. |
| `lock_factory` | `null` | The lock factory under which one process at a time refreshes an OAuth token: `null` for FrameworkBundle's `lock.factory` when `symfony/lock` is installed and `framework.lock` configured, and no lock otherwise; `false` for no lock; or the service of another `LockFactory`, which must be defined. [OAuth tokens](oauth-tokens.md#concurrent-refreshes) explains why. |
| `refresh_lock_ttl` | `30` | How many seconds at most a refresh holds its lock, so that a process that dies does not keep it; above the time the slowest token endpoint takes. |
| `log_events` | `false` | Whether every event is also written to the `logger` service, at `info` level. |

The KMS is checked when the container is built: without a client of the component, or with a `kms` that names no
service, the container stops with a message that says what to change; so does a `lock_factory` that names no service.
The master keys belong to the KMS: the bundle never reads them.

## Services

| Service | Public | Role |
|---|---|---|
| `Phil\UserSecretsBundle\Application\UserSecrets` | yes | Storing, listing, reading, revoking, removing, erasing |
| `Phil\UserSecretsBundle\Application\KeyRotation` | yes | Sealing again under the current master key |
| `Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository` | yes (alias) | The store chosen by `store` |

The parameter `phil_user_secrets.kms` holds the service of the KMS, and `phil_user_secrets.lock_factory` the service
of the lock factory. When that factory is defined, the private service `Phil\UserSecretsBundle\Infrastructure\Lock\SymfonyRefreshLock`
is the refresh lock of `UserSecrets`. The `logger` service, when defined, receives the failures of the revocation at
the service.

A service that implements `TokenRefresher` is tagged `phil_user_secrets.token_refresher`, one that implements
`TokenRevoker` is tagged `phil_user_secrets.token_revoker`, and one that implements `SecretAuditor` is tagged
`phil_user_secrets.auditor`, by autoconfiguration. With `phil/privacy-bundle` installed, the
module `user_secrets` is registered with its exporter and eraser tags; with `symfony/console`, the two commands.
