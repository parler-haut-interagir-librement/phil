# phil/user-secrets-bundle

**The keys people lend your application, kept like keys: sealed, theirs alone, and gone when they leave.**

phil/user-secrets-bundle keeps the credentials a person entrusts to a Symfony application for a third-party service — an
API key, an OAuth token with its refresh token. Each value is sealed by envelope encryption through Symfony's
KeyManagement component — a fresh data key wrapped by a master key of a KMS, the application's own or a local
`sodium://` one —, with the owner, the name and the service sealed beside it and authenticated with it, so a ciphertext
copied to another row does not open.
Every sealed value starts with a header that names its format and its algorithm, authenticated with it, and readers
open each value by it. Only a task run for the owner reads a secret, and it says what for; an expired OAuth token is
refreshed by the application's refresher before it is handed out, one process at a time under a lock, and saved by a
compare-and-swap. The owner lists their secrets without their values, revokes one — its value wiped, the row kept to
show when —, or removes it, and every secret leaves with the account through `phil/privacy-bundle`; an OAuth token that
goes is revoked at its service too, by the application's revoker. Every event goes to the application's audit hooks,
never with a value.

It is for Symfony applications that act for their members at other services: a writing tool that calls a language model
with the member's own key, a site that publishes to the member's repository or storage, a scheduler that syncs a
member's calendar at night.

It is a bundle built on Symfony's KeyManagement component behind a port, `ext-sodium`, Doctrine ORM and PSR-20;
`phil/privacy-bundle`, `symfony/lock` and `symfony/console` are optional. The KeyManagement component is experimental in
Symfony 8.2, outside Symfony's backward compatibility promise: one adapter of the bundle calls it. Master keys rotate
without downtime: add a key, make it current, run `phil:user-secrets:rotate`, then retire the old one once
`phil:user-secrets:rotate --dry-run` counts no secret left.

The design follows the layers of the monorepo: `Domain` holds the row `StoredSecret`, the credentials and value types,
and the port of the store; `Application` holds `UserSecrets`, `KeyRotation`, the port `EnvelopeCipher`, the envelope, the
audit trail and the ports
`TokenRefresher`, `TokenRevoker`, `RefreshLock` and `SecretAuditor`; `Infrastructure` holds the KeyManagement
adapter, the Doctrine and in-memory stores, the lock over Symfony's Lock component, the privacy module, the logger
auditor, the commands and the service wiring.

## Status

| Part | State |
|---|---|
| API keys and OAuth tokens sealed by envelope encryption through Symfony KeyManagement, bound to their row | Implemented and tested |
| A header naming the format and the algorithm of every sealed value, authenticated with it; values of the earlier format still read | Implemented and tested |
| The component's default client, a client named by its service, or a local `sodium://` one; KMS failures wrapped in the bundle's exceptions | Implemented and tested |
| Key rotation by command, between master keys and out of the earlier format | Implemented and tested |
| Reading only by a task of the owner, with its purpose | Implemented and tested |
| OAuth tokens refreshed by the application's refreshers before they expire | Implemented and tested |
| One refresh at a time under Symfony's Lock component, and a compare-and-swap on the row with or without it | Implemented and tested |
| Listing without values, revocation, removal | Implemented and tested |
| Export and erasure with the account through `phil/privacy-bundle` | Implemented and tested |
| Audit hooks, and an auditor that writes to a PSR-3 logger | Implemented and tested |
| Doctrine storage, and a store in memory for tests | Implemented and tested |
| Revocation at the service (RFC 7009) by the application's revokers, after revocation, removal and erasure, a failure logged and audited | Implemented and tested |
| Forms and screens for the owner | Left to the application, which builds them on `UserSecrets`: [what they call](doc/secrets.md#screens-for-the-owner) |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundles, the KMS and its master key, the table. |
| [Configuration](doc/configuration.md) | Every option of `phil_user_secrets`. |
| [Storing and reading secrets](doc/secrets.md) | `UserSecrets`: storing, listing, reading by a task, revoking, removing, erasing; the revocation at the service; the owner's screens. |
| [Encryption and key rotation](doc/encryption.md) | What is sealed, the sealed format and its header of format and algorithm, the KMS and the local `sodium://` one, rotating keys, what the encryption does not protect against. |
| [OAuth tokens](doc/oauth-tokens.md) | Tokens, their expiry, concurrent refreshes and the lock, writing a `TokenRefresher` and a `TokenRevoker`. |
| [Audit and privacy](doc/audit-and-privacy.md) | The events, writing a `SecretAuditor`, the logger auditor, export and erasure with `phil/privacy-bundle`. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, and when. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test an application that uses it. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/user-secrets-bundle
bin/console phil:user-secrets:generate-key     # put the output in USER_SECRETS_KEY_2026
```

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[k2026]=%env(USER_SECRETS_KEY_2026)%'

# config/packages/phil_user_secrets.yaml
phil_user_secrets:
    current_key: k2026
```

```php
$secrets->store($owner, SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of($submittedKey));

// Later, in a task run for that member:
$key = $secrets->apiKey(SecretAccess::forTaskOf($owner, 'draft a chapter'), SecretName::fromString('openai'));
$client->withApiKey($key->key->reveal());
```

## Requirements

PHP 8.5 or later with `ext-sodium` and `ext-openssl`, Symfony 8.2 with its KeyManagement component, Doctrine ORM 3.7;
optionally Symfony's Lock component.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
