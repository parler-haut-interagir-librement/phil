# phil/saml-sp-bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/saml-sp-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/saml-sp-bundle)

**Single sign-on for organisations in a Symfony application: people sign in through the SAML 2.0 identity provider
of their organisation — Microsoft Entra ID, Okta or any other — and their account is created at their first sign-in.**

phil/saml-sp-bundle puts phil/saml-sp in Symfony. Each organisation is an **identity provider** of the configuration,
under a key its routes carry: its metadata from an address — fetched through phil/safe-http and cached —, a file or the
XML itself, a preset of claim names (Microsoft Entra ID, Okta, LDAP), the roles its groups give, and what its responses
must be. Each one gets four routes: the **metadata** of the service provider, which its administrator imports, the
**login** that sends the person there, the **assertion consumer service** the response is posted to, and the **logout
service**.

The **`SamlAuthenticator`** of Symfony Security reads the response with every check of phil/saml-sp — signatures on the
elements read, signature wrapping, destination, audience, the request answered, times, replay — and signs the person in.
An assertion is accepted once, on any number of servers: the accepted ones go to a table of the application's Doctrine
DBAL connection, one atomic insert each, or to a shared cache pool without a database; in production, a cache pool local
to each server is refused when the container is built. The sign-in is bound by a cookie to the browser that started it,
and the person goes back to the local path they started from. Their account comes from **`SamlAccounts`**, the port the
application implements to find a person and to create their account at the first sign-in; without it, a **`SamlUser`**
lives in the session with the roles of the configuration. Symfony's logout carries on to the identity provider when it
has a logout service, and a logout the identity provider starts ends the session of the browser and is announced to the
application. Events tell the application of each sign-in, each account created, each logout and each refusal;
`phil:saml:check` warns before a certificate expires.

## Status

| Part | State |
|---|---|
| Configuration: service provider, keys, identity providers, presets, group roles, security options | Implemented and tested |
| Routes: metadata, login, assertion consumer service, logout service | Implemented and tested |
| `SamlAuthenticator`, `SamlUserProvider`, `SamlUser`, browser binding, local targets | Implemented and tested |
| `SamlAccounts`: accounts found or created at the first sign-in | Implemented and tested |
| Single logout initiated by the application or by the identity provider | Implemented and tested |
| Accepted assertions in a database table by default, a cache pool otherwise, a local pool refused in production | Implemented and tested |
| Events, `phil:saml:check`, Flex recipe, Stimulus controller of the HTTP-POST binding | Implemented and tested |
| Discovery page that lets a person pick their organisation, SCIM provisioning | Not written |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Installing, the key pair, the firewall and the routes. |
| [Configuration](doc/configuration.md) | Every option, with its default. |
| [Microsoft Entra ID](doc/entra-id.md) | Registering the application in Microsoft Entra ID, step by step. |
| [Okta](doc/okta.md) | Registering the application in Okta, step by step. |
| [Accounts and roles](doc/accounts.md) | `SamlAccounts`, the account created at the first sign-in, `SamlUser`, roles from groups. |
| [Single logout](doc/logout.md) | Logout from the application and from the identity provider. |
| [Events and console](doc/events.md) | The events of the bundle and `phil:saml:check`. |
| [Security](doc/security.md) | What the bundle checks and binds, the cookie, the cache, the keys. |
| [Extension points](doc/extension-points.md) | The ports and services an application replaces, and bridging to other sign-in packages. |
| [Testing](doc/testing.md) | The tests and the test application. |
| [Reference](doc/reference.md) | Every public type, route and service. |

## Quick start

```sh
composer require phil/saml-sp-bundle
```

```yaml
# config/packages/phil_saml_sp.yaml
phil_saml_sp:
    signing:
        certificate: '%env(file:resolve:SAML_SP_CERTIFICATE)%'
        private_key: '%env(file:resolve:SAML_SP_PRIVATE_KEY)%'
    identity_providers:
        acme:
            metadata_url: 'https://login.microsoftonline.com/<tenant>/federationmetadata/2007-06/federationmetadata.xml?appid=<application>'
            preset: entra_id

# config/packages/security.yaml
security:
    providers:
        saml: { id: phil_saml_sp.user_provider }
    firewalls:
        main:
            provider: saml
            custom_authenticators: [Phil\SamlSpBundle\Infrastructure\Security\SamlAuthenticator]
            logout: { path: /logout }
```

A link to `/saml/acme/login?target=/courses` signs a person of Acme in and brings them to `/courses`.

## Requirements

PHP 8.5 or later; Symfony 8.2 or later with FrameworkBundle and SecurityBundle; phil/saml-sp. `symfony/console` for
`phil:saml:check`, StimulusBundle for the page of the HTTP-POST binding to post itself.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
