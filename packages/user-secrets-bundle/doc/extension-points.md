# Extension points

What an application implements, configures, replaces or relies on.

## Ports

### `TokenRefresher`

`Phil\UserSecretsBundle\Application\Contract\TokenRefresher` — refreshes an expired OAuth token of one service. A service
implementing it is tagged `phil_user_secrets.token_refresher` by autoconfiguration. See
[OAuth tokens](oauth-tokens.md#writing-a-refresher).

| Method | Contract |
|---|---|
| `supports(ServiceName $service): bool` | Whether it refreshes this service's tokens |
| `refresh(ServiceName $service, OAuthToken $token): OAuthToken` | The new token, from the service's token endpoint; never logs either token |

### `TokenRevoker`

`Phil\UserSecretsBundle\Application\Contract\TokenRevoker` — revokes an OAuth token at the service that issued it,
once it is gone locally. A service implementing it is tagged `phil_user_secrets.token_revoker` by autoconfiguration.
See [OAuth tokens](oauth-tokens.md#writing-a-revoker).

| Method | Contract |
|---|---|
| `supports(ServiceName $service): bool` | Whether it revokes this service's tokens |
| `revoke(ServiceName $service, OAuthToken $token): void` | Revokes the token at the service — RFC 7009, the refresh token first when there is one; throws when the service did not. Never logs the token, nor puts it in an exception's message |

### `RefreshLock`

`Phil\UserSecretsBundle\Application\Contract\RefreshLock` — lets one process at a time refresh a given token.
`SymfonyRefreshLock`, over Symfony's Lock component, is wired when a lock factory is defined; an application replaces
it by setting the argument `$lock` of `UserSecrets`.

| Method | Contract |
|---|---|
| `synchronized(Owner $owner, SecretName $name, Closure $critical): mixed` | Runs `$critical` while no other process runs it for the same secret, and returns what it returns; releases the lock when it throws |

### `SecretAuditor`

`Phil\UserSecretsBundle\Application\Contract\SecretAuditor` — hears every change and every read. A service implementing
it is tagged `phil_user_secrets.auditor` by autoconfiguration; `LoggerAuditor` is shipped behind `log_events`. See
[Audit and privacy](audit-and-privacy.md).

| Method | Contract |
|---|---|
| `record(SecretEvent $event): void` | Handles a `SecretEvent` — its kind, owner, secret name, time and, for a read, purpose; never a value. Called after the change is saved; an exception goes through to the caller |

### `StoredSecretRepository`

`Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository` — the sealed secrets, a public alias of the store
`store` chooses. A store of the application's own keeps `toReseal()` bounded by its limit and past the secret `$after`, by
owner and name, and counts the same secrets by key in `countToReseal()`, for the key rotation;
reads the row from where it is kept in `fresh()`, past any cache; and saves in `replace()` only when the row still
holds the previous sealed value and is not revoked — one conditional `UPDATE` —, which the refresh of a token relies
on.

### `EnvelopeCipher`

`Phil\UserSecretsBundle\Application\Contract\EnvelopeCipher` — seals and opens under a named master key:
`KeyManagementCipher`, through Symfony's KeyManagement. The format is the bundle's: an application chooses the KMS,
not the cipher. See [Encryption and key rotation](encryption.md).

## Configuration

| Option | What it extends |
|---|---|
| `kms` | The client of KeyManagement that seals — any `DataKeyGeneratorInterface`: AWS KMS, Azure Key Vault, Google Cloud KMS, Vault Transit, a local `sodium://` one —; `null` for the component's default client |
| `current_key` | The master key every new value is sealed under |
| `refresh_leeway` | How early an OAuth token is refreshed |
| `lock_factory`, `refresh_lock_ttl` | The lock a refresh runs under, and for how long at most |
| `log_events` | Whether every event is also logged |
| `store`, `entity_manager` | Where secrets are kept |

See [Configuration](configuration.md).

## Services

| Service | How an application relies on it |
|---|---|
| `Phil\UserSecretsBundle\Application\UserSecrets` | Stores, lists, reads for a task of the owner, revokes, removes and erases, here and at the service; public |
| `Phil\UserSecretsBundle\Application\KeyRotation` | Seals again under the current key; public |

With `phil/privacy-bundle`, the module `user_secrets` answers access and erasure requests.

The screens of the owner — adding, listing, revoking — are the application's: the bundle ships no form, controller or
template. See [Storing and reading secrets](secrets.md#screens-for-the-owner).
