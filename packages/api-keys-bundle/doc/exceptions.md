# Exceptions

Every exception of the bundle implements `Phil\ApiKeysBundle\Domain\Exception\ApiKeysException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `Domain\Exception\MalformedToken` | `InvalidArgumentException` | `TokenFormat::parse()` reads a text of another length, prefix or separators, with a character no token holds, or whose checksum does not match; the message never repeats the text |
| `Domain\Exception\InvalidTokenValue` | `InvalidArgumentException` | a prefix out of shape, an owner or name out of bounds, a scope given twice, an expiry not after the creation or past the longest lifetime, a revoked or expired token rotated, a hash key shorter than 32 bytes, given twice or one key too many, a scope name out of shape |
| `Infrastructure\SecretScanning\Exception\UnreadableReport` | `InvalidArgumentException` | a report of GitHub's secret scanning or a document of its keys that is not JSON, not the shape expected, or past a bound; the endpoint answers `400`, or keeps no key |
| `Application\Exception\UnknownScope` | `InvalidArgumentException` | a scope the configuration does not declare; the message lists those it does |
| `Application\Exception\TokenNotFound` | `RuntimeException` | no token of that identifier, or another owner's |
| `Application\Exception\InvalidCredentials` | `RuntimeException` | `ApiTokens::verify()` refuses a token; `failure` is a `CredentialFailure`: `Malformed`, `Unknown`, `Revoked` or `Expired` |

The token handler turns `InvalidCredentials` into Symfony's `BadCredentialsException`, the previous exception kept, and
the authenticator answers `401`; the members' page turns `TokenNotFound` into `404`. A configuration out of shape fails
the container build with Symfony's `InvalidConfigurationException`.
