# Exceptions

The bundle's exceptions implement `Phil\UserSecretsBundle\Domain\Exception\UserSecretsException`. None carries a
secret's value or a key.

| Exception | Extends | Thrown when |
|---|---|---|
| `Domain\Exception\InvalidSecret` | `InvalidArgumentException` | An owner, a name, a service, a value or a purpose out of shape; a credential payload of the wrong shape; a rotation batch below 1 |
| `Domain\Exception\RevokedSecret` | `RuntimeException` | A revoked secret read or re-keyed |
| `Application\Exception\SecretNotFound` | `RuntimeException` | The owner has no secret of that name — including when another owner's task asks |
| `Application\Exception\WrongCredentialKind` | `LogicException` | An API key read as a token, or a token as a key |
| `Application\Exception\ExpiredToken` | `RuntimeException` | An OAuth token expired that cannot be refreshed |
| `Application\Exception\RefreshConflict` | `RuntimeException` | A refreshed OAuth token that could not be saved, its row changed by other processes at each of `UserSecrets::REFRESH_ATTEMPTS` attempts |
| `Application\Exception\UnreadableSecret` | `RuntimeException` | A ciphertext that does not open: of no known format or algorithm, its header changed, malformed, altered, sealed under a master key the KMS does not hold, copied from another row, holding no credential, or one the KMS did not open — unreachable, refusing —, its exception kept as the previous one |
| `Application\Exception\SealingFailed` | `RuntimeException` | A value the KMS did not seal — unreachable, refusing —, its exception kept as the previous one |
| `Application\Exception\UnknownKey` | `RuntimeException` | A current key the KMS has no master key for |

Examples of messages:

```text
The owner has no secret "openai".
The secret "openai" was revoked: store a new value to use it again.
The OAuth token "github" has expired and cannot be refreshed: the owner must connect the service again.
The KMS has no master key "k2027" to seal with: check phil_user_secrets.current_key.
The sealed value of secret "openai" names no known algorithm.
```

A failure of a `TokenRevoker` is never thrown: it is logged and audited, as [storing and reading secrets](secrets.md#revocation-at-the-service)
explains.

A configuration without `current_key` fails when the container is built, with Symfony's `InvalidConfigurationException`;
without a client of the KeyManagement component, or with a `kms` that names no service, or with a `lock_factory` that
names no service, with a `LogicException` that says what to change. A failure of the KMS itself reaches the caller as
`UnreadableSecret` or `SealingFailed`, with the KMS's own exception as the previous one.
