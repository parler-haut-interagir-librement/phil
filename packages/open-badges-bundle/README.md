# phil/open-badges-bundle

**Open Badges in a Symfony application: the issuer configured once, its profile and keys published, its revocations
served, and a page where anyone verifies a badge.**

phil/open-badges-bundle puts [`phil/open-badges`][open-badges] in a Symfony application. The configuration names the
issuer and its key — kept as a secret —, and how its credentials are secured: as JSON Web Tokens, or with Data Integrity
proofs. The bundle then serves what anyone needs to verify them: the issuer's profile at its identifier, its public
keys, and the status lists that say which credentials were revoked or suspended. Its verification page checks a
credential pasted, or a badge image with the credential baked in — the application's own, and, when it asks, those of
other issuers, whose Ed25519 or RSA keys are read from their profiles or `did:web` documents through a guarded HTTP
client.

It is for the application that awards badges or certificates and wants them to hold outside of it. The application
decides who earns what and keeps what it issued; the bundle gives it the builders, already configured, and the
public side of the issuer.

## Status

| Part | State |
|---|---|
| The issuer and its key from configuration (EdDSA or RS256), the builders of credentials, endorsements, learner records and status lists | Implemented and tested |
| Securing as JSON Web Tokens or Data Integrity proofs | Implemented and tested |
| The issuer's profile with its Multikey, its JSON Web Key Set | Implemented and tested |
| Bitstring Status Lists served from the application's `StatusLists` | Implemented and tested |
| The verification page: tokens, documents, baked PNG and SVG images, the recipient's identity | Implemented and tested |
| The checks of the page counted against a limiter of `phil/rate-limit-bundle`, by configuration | Implemented and tested |
| The JSON-LD contexts the application provides, read from its files | Implemented and tested |
| The build refused, with the options to set, when no issuer is configured | Implemented and tested |
| Verifying the credentials of issuers the application does not configure: their profile read through `phil/safe-http`, its keys cached, trusted issuers optional | Implemented, tested and fuzzed |
| Ed25519 and RSA keys of other issuers, as `Multikey` or `JsonWebKey`; issuers known by a `did:web`, their DID document read where the method places it | Implemented, tested and fuzzed |
| 1EdTech conformance testing | In progress: this version is not certified; [Conformance](doc/conformance.md) says what is tested and how to verify a credential today |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the key, the routes. |
| [Configuration](doc/configuration.md) | Every option, with its default. |
| [Issuing](doc/issuing.md) | The services an application injects to issue and secure credentials. |
| [Publishing](doc/publishing.md) | The profile, the keys and the status lists the routes serve. |
| [Verification page](doc/verification-page.md) | What the page checks and shows, its layout and translations. |
| [Other issuers](doc/remote-issuers.md) | Reading the keys of issuers the application does not configure, `did:web` included, its bounds and its refusals. |
| [Conformance](doc/conformance.md) | Where 1EdTech conformance testing stands, what is tested, and how to verify a credential today. |
| [Extension points](doc/extension-points.md) | `StatusLists`, the layout, the trusted keys, the verification methods, the contexts, the limit on the checks. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that issues badges. |
| [Reference](doc/reference.md) | Every class of the bundle, with its role. |

## Quick start

```sh
composer require phil/open-badges-bundle
```

```yaml
# config/packages/phil_open_badges.yaml
phil_open_badges:
    issuer:
        id: https://example.org/open-badges/issuer
        name: Example
        url: https://example.org
    key:
        secret: '%env(base64:OPEN_BADGES_SEED)%'
    securing: data_integrity
```

```yaml
# config/routes/phil_open_badges.yaml
phil_open_badges:
    resource: '@PhilOpenBadgesBundle/config/routes.php'
    prefix: /open-badges
phil_open_badges_verification:
    resource: '@PhilOpenBadgesBundle/config/verification_routes.php'
    prefix: /open-badges
```

```php
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadgesBundle\Application\Securing;

$credential = $credentials->credential($achievement, $recipient, 'urn:uuid:'.$uuid, $awardedAt);
$secured = $securing->secure($credential);    // a SecuredCredential: ->content, ->mediaType
```

## Conformance

1EdTech conformance testing is in progress, and this version is not certified by 1EdTech. Credentials are verified
today by the bundle's verification page, by the `CredentialVerifier` service, by any Verifiable Credentials 2.0
verifier that reads VC-JWT or `eddsa-rdfc-2022`, or by running 1EdTech's validator against a deployed issuer; see
[Conformance](doc/conformance.md).

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, `phil/open-badges` 0.1.1 or later, `phil/url` and `phil/safe-http`. The
verification page needs TwigBundle; reading other issuers' profiles and DID documents, a cache pool.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).

[open-badges]: https://github.com/parler-haut-interagir-librement/open-badges
