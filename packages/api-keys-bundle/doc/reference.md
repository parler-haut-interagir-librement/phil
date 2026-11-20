# Reference

Every type of the bundle, by layer.

## Domain

| Type | Role |
|---|---|
| `Domain\Token\TokenFormat` | Generates identifiers and secrets, writes and reads tokens of a prefix |
| `Domain\Token\TokenOwner` | Who a token acts for: a type, `user` for a member, and an identifier |
| `Domain\Token\PresentedToken` | A token read from a client: identifier and secret, printed as its identifier, never serialized |
| `Domain\Token\Base62` | Internal: the alphabet, unbiased random strings, fixed-width checksums |
| `Domain\Model\ApiToken` | The row of `phil_api_token` and its rules: typed owner, expiry, revocation, grace, throttled use |
| `Domain\Contract\ApiTokenRepository` | The port of the store |
| `Domain\Event\TokenCreated`, `TokenRevoked`, `TokenUsed`, `TokenLeaked` | The events, each with the token's identifier |
| `Domain\Exception\ApiKeysException` | What every exception of the bundle implements |
| `Domain\Exception\MalformedToken`, `InvalidTokenValue` | A text that is not a token; a value a token cannot take |

## Application

| Type | Role |
|---|---|
| `Application\ApiTokens` | Creates, finds, revokes — one, or every token of an owner —, rotates, purges and verifies tokens, and revokes those reported leaked |
| `Application\IssuedToken` | A token just created and its text, shown once, never serialized |
| `Application\TokenHasher` | The HMAC-SHA-256 kept in place of the secret, naming its algorithm and version, under the first key of an ordered list, checked against every key in constant time |
| `Application\OwnerTypes` | The owner types a token may act for, and the scopes each may hold |
| `Application\ScopeCatalogue`, `ScopeDefinition` | The declared scopes, their descriptions and labels |
| `Application\CredentialFailure` | Why a token was refused: malformed, unknown, revoked, expired |
| `Application\Contract\ClientAddressAnonymizer` | What the row keeps of the client's address |
| `Application\Exception\InvalidCredentials`, `TokenNotFound`, `UnknownScope` | The refusals of the service |

## Infrastructure

| Type | Role |
|---|---|
| `Infrastructure\Attribute\RequiresScope` | The scopes a controller or an action requires |
| `Infrastructure\Security\ApiTokenHandler` | The token handler of Symfony's `access_token` authenticator: a token to its owner's badge |
| `Infrastructure\Security\TokenAuthenticationListener` | The roles of the scopes and the token's identifier, on the security token and the request |
| `Infrastructure\Security\ScopeVoter` | Grants `API_SCOPE` on a scope the token holds |
| `Infrastructure\Security\RequiresScopeListener` | Checks `#[RequiresScope]` before the controller |
| `Infrastructure\Security\ProblemErrorResponder` | The `401` as problem details: an entry point and a failure handler |
| `Infrastructure\SecretScanning\GitHub\GitHubSecretScanningController` | The endpoint of GitHub's secret scanning |
| `Infrastructure\SecretScanning\GitHub\GitHubPublicKeys` | GitHub's keys, fetched and kept in a cache pool |
| `Infrastructure\SecretScanning\GitHub\LeakReport` | The body GitHub sends, read within bounds |
| `Infrastructure\SecretScanning\Exception\UnreadableReport` | A report or a document of keys that cannot be read |
| `Infrastructure\Doctrine\DoctrineApiTokenRepository` | The store in the table, through the entity manager |
| `Infrastructure\Memory\InMemoryApiTokenRepository` | The store in memory, emptied between requests |
| `Infrastructure\RateLimit\TokenKey` | The key strategy of a rate limiter, by token |
| `Infrastructure\IpAnonymizer\AnonymizedClientAddress` | The address through a strategy of `phil/ip-anonymizer` |
| `Infrastructure\Console\CreateTokenCommand`, `ListTokensCommand`, `RevokeTokenCommand`, `PurgeExpiredTokensCommand` | The commands |
| `Infrastructure\Form\CreateTokenType` | The form of the members' page, offering the scopes an owner type may hold |
| `Infrastructure\Http\TokenPageController` | The members' page, the page of another owner's tokens behind `MANAGE`, and the revocation |
| `Infrastructure\DependencyInjection\ApiKeysServices` | The wiring of the configuration |
| `PhilApiKeysBundle` | The bundle: its configuration, the Doctrine mapping, the services |
