# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\UserSecretsBundle\PhilUserSecretsBundle` | bundle | The configuration `phil_user_secrets`, the Doctrine mapping, the privacy module |
| `Phil\UserSecretsBundle\Domain\ValueObject\Owner` | `final readonly class` | The person a secret belongs to |
| `Phil\UserSecretsBundle\Domain\ValueObject\SecretName` | `final readonly class` | A secret's name among its owner's |
| `Phil\UserSecretsBundle\Domain\ValueObject\ServiceName` | `final readonly class` | The service a secret opens |
| `Phil\UserSecretsBundle\Domain\ValueObject\SecretValue` | `final readonly class` | A secret string that does not show itself |
| `Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret` | `final readonly class` | A key identifier and a ciphertext, whose first byte names its format |
| `Phil\UserSecretsBundle\Domain\Encryption\SealedFormat` | enum | The version of a sealed value: `Algorithm` (3), written; `KeyManagement` (2), read |
| `Phil\UserSecretsBundle\Domain\Encryption\SealedAlgorithm` | enum | The algorithm of a sealed value, its second byte: `KeyManagementAes256Gcm` (1) |
| `Phil\UserSecretsBundle\Domain\Contract\Credential` | interface | An API key or an OAuth token |
| `Phil\UserSecretsBundle\Domain\Credential\CredentialKind` | enum | `api_key`, `oauth_token`; a credential from its payload |
| `Phil\UserSecretsBundle\Domain\Credential\ApiKey` | `final readonly class` | A key sent as is |
| `Phil\UserSecretsBundle\Domain\Credential\OAuthToken` | `final readonly class` | An access token, refresh token, expiry and scopes |
| `Phil\UserSecretsBundle\Domain\Model\StoredSecret` | entity | The row of a secret |
| `Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository` | interface | The store of secrets |
| `Phil\UserSecretsBundle\Domain\Exception\UserSecretsException` | interface | Every exception of the bundle |
| `Phil\UserSecretsBundle\Domain\Exception\InvalidSecret` | `final class` | A value out of shape |
| `Phil\UserSecretsBundle\Domain\Exception\RevokedSecret` | `final class` | A revoked secret read |
| `Phil\UserSecretsBundle\Application\UserSecrets` | `final readonly class` | Storing, listing, reading, revoking, removing, erasing |
| `Phil\UserSecretsBundle\Application\KeyRotation` | `final readonly class` | Sealing again under the current master key |
| `Phil\UserSecretsBundle\Application\RotationReport` | `final readonly class` | What a key rotation did: re-sealed, skipped, unreadable |
| `Phil\UserSecretsBundle\Application\SecretAccess` | `final readonly class` | A task run for an owner, with its purpose |
| `Phil\UserSecretsBundle\Application\SecretSummary` | `final readonly class` | A secret without its value |
| `Phil\UserSecretsBundle\Application\Contract\EnvelopeCipher` | interface | Envelope encryption under a named master key: the port to the KeyManagement component |
| `Phil\UserSecretsBundle\Infrastructure\KeyManagement\KeyManagementCipher` | `final readonly class` | Envelope encryption through a client of Symfony's component, its failures turned into the bundle's exceptions |
| `Phil\UserSecretsBundle\Application\Encryption\Envelope` | `final readonly class` | A credential sealed with its row behind a header of format and algorithm, opened by that header |
| `Phil\UserSecretsBundle\Application\Audit\AuditTrail` | `final readonly class` | Events handed to every auditor |
| `Phil\UserSecretsBundle\Application\Audit\SecretEvent` | `final readonly class` | One event of one secret |
| `Phil\UserSecretsBundle\Application\Audit\SecretEventKind` | enum | What happened |
| `Phil\UserSecretsBundle\Application\Contract\TokenRefresher` | interface | Renews an expired OAuth token |
| `Phil\UserSecretsBundle\Application\Contract\TokenRevoker` | interface | Revokes an OAuth token at its service |
| `Phil\UserSecretsBundle\Application\Contract\RefreshLock` | interface | One process at a time refreshes a token |
| `Phil\UserSecretsBundle\Application\Contract\SecretAuditor` | interface | An audit hook |
| `Phil\UserSecretsBundle\Application\Exception\SecretNotFound` | `final class` | No secret of that name |
| `Phil\UserSecretsBundle\Application\Exception\WrongCredentialKind` | `final class` | A secret read as another kind |
| `Phil\UserSecretsBundle\Application\Exception\ExpiredToken` | `final class` | A token that cannot be refreshed |
| `Phil\UserSecretsBundle\Application\Exception\RefreshConflict` | `final class` | A refreshed token that could not be saved over other processes' changes |
| `Phil\UserSecretsBundle\Application\Exception\UnreadableSecret` | `final class` | A ciphertext that does not open, or one the KMS did not open |
| `Phil\UserSecretsBundle\Application\Exception\SealingFailed` | `final class` | A value the KMS did not seal |
| `Phil\UserSecretsBundle\Application\Exception\UnknownKey` | `final class` | A current key the KMS lacks |
| `Phil\UserSecretsBundle\Infrastructure\Doctrine\DoctrineStoredSecretRepository` | `final readonly class` | The secrets in Doctrine |
| `Phil\UserSecretsBundle\Infrastructure\Memory\InMemoryStoredSecretRepository` | `final class` | The secrets in an array |
| `Phil\UserSecretsBundle\Infrastructure\Lock\SymfonyRefreshLock` | `final readonly class` | The refresh lock over Symfony's Lock component |
| `Phil\UserSecretsBundle\Infrastructure\Privacy\UserSecretsPrivacy` | `final readonly class` | The module `user_secrets` of `phil/privacy-bundle` |
| `Phil\UserSecretsBundle\Infrastructure\Audit\LoggerAuditor` | `final readonly class` | Events to a PSR-3 logger |
| `Phil\UserSecretsBundle\Infrastructure\Console\GenerateKeyCommand` | `final class` | `phil:user-secrets:generate-key` |
| `Phil\UserSecretsBundle\Infrastructure\Console\RotateKeysCommand` | `final class` | `phil:user-secrets:rotate` |
| `Phil\UserSecretsBundle\Infrastructure\DependencyInjection\UserSecretsServices` | `final class` | The services of the configuration |
| `Phil\UserSecretsBundle\Infrastructure\DependencyInjection\KmsPass` | `final readonly class` | Stops the container when the KMS is not defined |
| `Phil\UserSecretsBundle\Infrastructure\DependencyInjection\RefreshLockPass` | `final readonly class` | Gives `UserSecrets` the refresh lock when a lock factory is defined |
