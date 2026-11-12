# phil/identity-client-bundle

**Sign the users of a Symfony application in through any OpenID Connect provider, hardened.**

phil/identity-client-bundle configures the `oidc_login` authenticator of Symfony from one block of configuration. It
writes the firewall, pins the signature algorithm and adds what a relying party needs beyond the sign-in flow: a
validated `return_to`, a back-channel logout that accepts each token once, a session cap, step-up by `acr_values` and
`max_age`, and token refresh that never presents a rotated refresh token twice.

It is for Symfony applications that delegate authentication to an OpenID Connect provider. The flow itself, the state
and nonce, discovery, the signature checks and client authentication all stay Symfony's own.

## Status

| Part | State |
|---|---|
| Semantic configuration composing the `oidc_login` firewall | Implemented and tested |
| Provisioning on `sub`, kept claims, tokens dropped from the session | Implemented and tested |
| `return_to` validated against declared origins | Implemented and tested |
| Back-channel logout with `jti` replay guard, session cap | Implemented and tested |
| Step-up: voter, `acr_values`, `max_age`, RFC 9470 challenge | Implemented and tested |
| Serialized token refresh | Implemented and tested |

An independent security review is expected before a first release.

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the firewall, the routes. |
| [Configuration](doc/configuration.md) | Every key, and what it composes. |
| [Provisioning](doc/provisioning.md) | `UserProvisioner`, the identity, what the session keeps. |
| [Return to](doc/return-to.md) | The parameter, the policy, where a sign-in lands. |
| [Logout](doc/logout.md) | Back-channel logout, the replay guard, the session cap. |
| [Step-up](doc/step-up.md) | Assurance levels, the voter, the challenge. |
| [Token refresh](doc/token-refresh.md) | The lock, the shared outcome, family revocation. |
| [Extension points](doc/extension-points.md) | Ports, events, services. |
| [Security](doc/security.md) | The threats covered and what stays with the application. |
| [Testing](doc/testing.md) | How the bundle is tested. |

## Quick start

```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            lazy: true
```

```yaml
# config/packages/phil_identity_client.yaml
phil_identity_client:
    issuer: 'https://id.example.org'
    client_id: 'example-site'
    client_secret: '%env(OIDC_CLIENT_SECRET)%'
    claims: ['name', 'email']
```

```yaml
# config/routes/security.yaml
oidc_login:
    resource: security.route_loader
    type: service
```

A link to `/oidc/start?return_to=/account` signs the user in and lands on the account page.

## Requirements

PHP 8.5 or later with `ext-sodium` and `ext-uri`; Symfony 8.2's security bundle and components, framework bundle, HTTP
client, cache, clock and lock; `web-token/jwt-library` 4.2, which the `oidc_login` authenticator runs on.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
