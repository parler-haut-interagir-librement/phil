# phil/api-keys-bundle

**Access tokens for a public API, for members, organisations or applications: shown once, kept as a keyed hash,
scoped, expiring, revocable.**

phil/api-keys-bundle lets the members of a Symfony application create tokens for its public API — a deploy script, a
spreadsheet, a home automation box — and lets the API know who calls and what for. A token acts for a member, or for
another owner type the configuration declares, such as an organisation or an application, loaded by its own user
provider and limited to the scopes that type may hold. A token reads
`acme_3kTMd9qXbW1z_<32 characters><6 characters>`: a prefix that names the application, so secret scanners recognise
it in a repository; an identifier that finds its row; a secret drawn from the secure source of `random_bytes()`; and a
CRC-32 checksum that refuses a mistyped or truncated token offline, before any lookup. The text is shown once, at
creation; the table keeps only the HMAC-SHA-256 of the secret under a key from the secrets vault, compared in constant
time, written with the name of its algorithm and version. The keys are an ordered list: the first hashes, every one
verifies, so the key rotates without cutting the tokens issued under the previous one.

Every token holds scopes the configuration declares, with a description for developers and a translatable label for
members, and every token expires, within a longest lifetime the configuration sets. A token is revoked at once — one,
or every token of an owner —, or rotated: a successor with the same scopes, the old token revoked or kept for a grace
period while the client switches. Its last use is written at most once per interval, with the client's address only as
`phil/ip-anonymizer` anonymizes it.

A stateless firewall gives Symfony's `access_token` authenticator the bundle's token handler: `Authorization: Bearer
<token>` or another header, the user loaded by the token's owner through the provider of its type, the scopes as roles
and attributes, a voter and
`#[RequiresScope('projects:write')]` on controllers. A refused token is answered `401` with the challenge of RFC 6750
— as RFC 9457 problem details when `phil/api-contract-bundle` is installed —, a missing scope `403 insufficient_scope`.
Rate limits and quotas count per token through `phil/rate-limit-bundle` and `phil/usage-meter-bundle`, with their
`RateLimit` headers. Commands create, list, revoke and purge tokens, and an optional page lets members manage theirs,
and those of an organisation or an application when the application's voters allow them. An optional endpoint receives
the tokens GitHub's secret scanning finds in public repositories, verifies GitHub's signature and revokes them. The
events carry identifiers only, for an audit trail.

It is a bundle built on Symfony Security, Doctrine ORM and PSR-20; the console, the form component, Twig,
`phil/api-contract-bundle`, `phil/rate-limit-bundle`, `phil/ip-anonymizer`, and Symfony's HTTP client and cache for
the secret scanning endpoint, are optional. Its services keep no state between requests and serve a long-running
worker; the store in memory is emptied between requests.

The design follows the layers of the monorepo: `Domain` holds the token format, the owner, the row `ApiToken`, the
port of the store and the events; `Application` holds `ApiTokens`, the hasher, the scope catalogue, the owner types and
the port of the address anonymizer; `Infrastructure` holds the Doctrine and in-memory stores, the token handler and the listener of the roles,
the voter and the attribute, the endpoint of GitHub's secret scanning, the integrations, the commands, the form and the
page, and the service wiring.

## Status

| Part | State |
|---|---|
| Token format with prefix, identifier, secret and checksum; strict reader, fuzzed | Implemented and tested |
| Keyed hash only, naming its algorithm and version, compared in constant time; text shown once; ordered keys for rotation | Implemented and tested |
| Typed owners — members, organisations, applications — loaded by their own user providers, scopes per type, revocation of every token of an owner | Implemented and tested |
| Scopes declared in configuration, descriptions and translatable labels | Implemented and tested |
| Required expiry within a longest lifetime; revocation; rotation with grace; purge | Implemented and tested |
| Last use written once per interval, address anonymized or not kept | Implemented and tested |
| Token handler for Symfony's `access_token` authenticator, scope roles and attributes, voter, `#[RequiresScope]`, RFC 6750 challenges | Implemented and tested |
| Endpoint of GitHub's secret scanning: signature verified, leaked tokens revoked; disabled by default | Implemented and tested |
| Problem details with `phil/api-contract-bundle`, rate limits by token with `phil/rate-limit-bundle` | Implemented and tested |
| Commands; members' page and the page of another owner's tokens behind a voter, in English and French | Implemented and tested |
| Doctrine store and store in memory, emptied between requests | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the key, the table, the firewall. |
| [Configuration](doc/configuration.md) | Every option of `phil_api_keys`. |
| [Tokens](doc/tokens.md) | The format, what is stored, creating, rotating, revoking, purging, the last use. |
| [Authentication and scopes](doc/authentication.md) | The firewall, the roles and attributes, the voter, `#[RequiresScope]`, the answers. |
| [Secret scanning](doc/secret-scanning.md) | The endpoint GitHub posts leaked tokens to: enabling it, the signature, the revocation, the keys. |
| [The members' page](doc/members-page.md) | The routes, the form, the templates, the translations. |
| [Integrations](doc/integrations.md) | Quotas, problem details, anonymized addresses, the events for an audit trail. |
| [Console](doc/console.md) | The four commands. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, and when. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The bundle's tests, fuzz targets and benchmark, and how to test an application that uses it. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/api-keys-bundle
bin/console secrets:set API_KEYS_HASH_KEY     # 32 random bytes or more
```

```yaml
# config/packages/phil_api_keys.yaml
phil_api_keys:
    prefix: acme
    hash_key: '%env(API_KEYS_HASH_KEY)%'
    scopes:
        projects:read: { description: 'Read the projects', label: 'scope.projects_read' }
        projects:write: { description: 'Create and change projects', label: 'scope.projects_write' }

# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api/
            stateless: true
            access_token:
                token_handler: phil_api_keys.token_handler
                token_extractors: [phil_api_keys.token_extractor]
                realm: api
```

```php
#[RequiresScope('projects:read')]
final class ProjectController
{
    #[RequiresScope('projects:write')]
    public function update(): Response
    {
        // ...
    }
}
```

```sh
curl -H "Authorization: Bearer acme_3kTMd9qXbW1z_…" https://example.com/api/projects
```

## Requirements

PHP 8.5 or later, Symfony 8.2 with its Security components, Doctrine ORM 3.7.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
