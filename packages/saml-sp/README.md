# phil/saml-sp

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/saml-sp/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/saml-sp)

**Single sign-on for the people of an organisation through its SAML 2.0 identity provider — Microsoft Entra ID, Okta
or any other: a service provider that verifies the signed assertions and maps their attributes to a user.**

phil/saml-sp is a plain PHP library on `ext-dom` and `ext-openssl`. An application describes itself as a
**`ServiceProvider`** — its entity ID, its assertion consumer service, its logout service, its key pairs — and each
organisation it accepts as an **`IdentityProvider`**: the metadata read by `MetadataReader` from XML or fetched by
`MetadataLoader` from its address through phil/safe-http and cached, the **`AttributeMap`** of its claims (presets for
Microsoft Entra ID and Okta), and what its responses must be.

`MessageFactory` writes the **`AuthnRequest`**, and `MessageSender` puts it on the HTTP-Redirect binding, signed in the
query, or on HTTP-POST, signed inside. **`ResponseProcessor`** reads the response posted back and returns a
**`SamlIdentity`** — the name identifier, the session at the identity provider, the attributes and the mapped fields —,
or refuses it with a `SamlException`. It verifies XML signatures as SAML signs them and nothing looser: exclusive C14N,
RSA with SHA-256 and above, SHA-1 refused unless allowed, the reference resolving to the very element read, every ID
unique and exactly one assertion, so that signature wrapping finds nothing to wrap. It checks the status, the issuer,
the destination, the recipient, the audience, the request answered, the times within a clock skew, and refuses an
assertion presented twice. An `EncryptedAssertion` is decrypted with RSA-OAEP and AES-GCM or AES-CBC. Documents are
read with no DOCTYPE, no entity and within a budget of bytes, depth and nodes.

`LogoutProcessor` and the logout messages of `MessageFactory` carry single logout both ways, and
`ServiceProviderMetadata` writes the metadata an identity provider imports. The library keeps no state between calls:
the requests waiting for their response and the assertions already accepted live behind two ports, with PSR-6
implementations, so that it serves a long-running worker and several servers.

## Status

| Part | State |
|---|---|
| `SafeXml`, `XmlBudget`: documents read without DOCTYPE nor entity, bounded | Implemented and tested |
| `SignatureVerifier`, `Signer`: enveloped XML signatures, exclusive C14N, RSA-SHA256 and above | Implemented and tested |
| `Decrypter`: encrypted assertions, RSA-OAEP with AES-GCM or AES-CBC | Implemented and tested |
| `MessageFactory`, `MessageSender`, `HttpRedirect`, `HttpPost`: requests and logout messages on both bindings | Implemented and tested |
| `ResponseProcessor`: the checks of the Web Browser SSO profile, replay included | Implemented and tested |
| `LogoutProcessor`: single logout initiated by the service provider or by the identity provider | Implemented and tested |
| `MetadataReader`, `MetadataLoader`, `SafeHttpMetadataFetcher`, `ServiceProviderMetadata` | Implemented and tested |
| `AttributeMap` with the Microsoft Entra ID, Okta and LDAP presets, `SamlIdentity` | Implemented and tested |
| `DbalReplayGuard`: accepted assertions in a database table, one atomic insert each | Implemented and tested |
| `CacheReplayGuard`, `CachePendingRequests` and their in-memory counterparts | Implemented and tested |
| Artifact binding, ECP, encrypted name identifiers, SCIM provisioning | Not written |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and building the objects without a framework. |
| [Signing in](doc/sign-in.md) | The request, the response, the identity, unsolicited responses, encryption. |
| [Metadata](doc/metadata.md) | Reading the metadata of an identity provider, its cache, and writing the service provider's. |
| [Attributes](doc/attributes.md) | The attribute map, its presets, and the fields of a `SamlIdentity`. |
| [Single logout](doc/logout.md) | Logout initiated by the service provider and by the identity provider. |
| [The replay store](doc/replay-store.md) | The database, cache and in-memory replay guards, the table, its migration and its purge. |
| [Security](doc/security.md) | Every check, the defences against signature wrapping, XML attacks and replay, and the keys. |
| [Extension points](doc/extension-points.md) | The ports, the stores and the options an application chooses or replaces. |
| [Testing](doc/testing.md) | The tests, the test keys and fixtures, the fuzz target and the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/saml-sp
```

```php
use Phil\SamlSp\Attribute\AttributeMap;
use Phil\SamlSp\Attribute\UserField;
use Phil\SamlSp\Binding\MessageSender;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Metadata\MetadataReader;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Store\CacheReplayGuard;
use Phil\Url\Url;

$sp = new ServiceProvider('https://app.example.org/saml/metadata', Url::fromString('https://app.example.org/saml/acs'), signing: $keyPair);
$idp = new IdentityProvider(new MetadataReader()->read($federationMetadataXml), AttributeMap::entraId());

// Sending the person to the identity provider: remember the ID of the request, redirect the browser.
$request = new MessageFactory($clock)->authnRequest($sp, $idp);
$location = MessageSender::send($request, $sp->signing)->url;

// Reading the response posted back to the assertion consumer service.
$identity = new ResponseProcessor($clock, new CacheReplayGuard($cachePool))->process($_POST['SAMLResponse'], $idp, $sp, $request->id);
$identity->subject();                  // the stable identifier of the person at the identity provider
$identity->value(UserField::Email);
```

## Requirements

PHP 8.5 or later with the `dom`, `libxml`, `openssl`, `sodium` and `zlib` extensions; `phil/url` and `phil/safe-http`;
the PSR-6 and PSR-20 interfaces and the Symfony cache and service contracts. In a Symfony application,
phil/saml-sp-bundle adds the routes, the authenticator and the configuration.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
