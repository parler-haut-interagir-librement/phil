# phil/identity-provider-bundle

**An open identity brick for Symfony: accounts, one module per way to sign in, and an OpenID Connect provider.**

phil/identity-provider-bundle keeps accounts — an address and whether it was verified, a name, a password hashed with
Argon2id —, and every way to prove one: passwords checked against breaches by `phil/passwords`, passkeys (WebAuthn),
an authenticator application (TOTP) with recovery codes, sign-in links by email through the `login_link` authenticator
of Symfony Security, a phone number verified by a text message, and identities of federated providers whose packages
plug into a port. It asks for a second step when an account has an authenticator application, asks again before
sensitive actions on the sudo mode of Symfony Security 8.2, lists the devices and sessions of an account and closes
them from another device, and warns of a sign-in from a new device.

It is also an OpenID Connect provider written on the components of Symfony, its tokens signed and verified by
`web-token/jwt-library`: discovery, a key set that rotates, the authorization code flow with PKCE, ID and access
tokens, refresh tokens rotated with reuse detection and bound to their session, clients that authenticate by a secret
or by a client assertion signed with their own key (`private_key_jwt`), the `client_credentials` grant for tokens of a
resource, userinfo, revocation (RFC 7009), introspection (RFC 7662), RP-initiated logout, consent per client, and
clients registered in the database. It passes the Basic OP, Config OP and
RP-Initiated Logout OP plans of the OpenID Foundation's conformance suite, run in Docker by `tests/Conformance/run.sh`.

Each method is a module the configuration turns on or off, with its own routes, tables and pages: a site that does not
want sign-in links or text messages turns them off, and they cannot be reached. The bundle keeps the minimum about
people — no address of their connection, no token of a federated provider — and its events carry identifiers only.

## Status

| Part | State |
|---|---|
| Accounts: registration without telling who has an account, limited per client and per address, verified addresses by signed links confirmed by a form, links of the emails on the canonical address of the site, the account page | Implemented and tested |
| Passwords: Argon2id, strength and breach checks by `phil/passwords`, reset by a link signed over the password and used once, other sessions closed at each change, progressive lock, sign-in limiter per identifier for `login_throttling` | Implemented and tested |
| Passkeys: registration, sign-in, rename, removal; the last way to sign in kept | Implemented and tested |
| Authenticator application (TOTP), recovery codes, the second step of a sign-in | Implemented and tested |
| Sign-in links by email (`login_link` of Symfony Security) | Implemented and tested |
| Phone number verified by a text message, through the Notifier component or an `SmsSender` of your own | Implemented and tested |
| Step-up on Symfony 8.2: `PHIL_STEP_UP`, `AuthenticationMethodBadge` proofs (RFC 8176), `requestReAuthentication()` | Implemented and tested |
| Devices and sessions: list, one or all closed from another device, risky sign-in warned | Implemented and tested |
| Federated identities through the port `ExternalProvider`, linked without merging on an unverified address | Implemented and tested |
| OpenID Connect provider: discovery, JWKS with rotation, code flow with PKCE, tokens signed by `web-token/jwt-library`, refresh rotation, userinfo, revocation, introspection, logout, consent | Implemented and tested |
| Doctrine stores on SQLite, MySQL 8.4 and PostgreSQL 17; memory stores for tests | Implemented and tested |
| Templates drawn with the recipes of the kit, a Stimulus controller for passkeys | Implemented and tested |
| Audit trail through `phil/audit-bundle` when installed; secrets encrypted by the KeyManagement component, in clear only when the configuration says so | Implemented and tested |
| OpenID Foundation conformance suite: Basic OP, Config OP and RP-Initiated Logout OP plans, in Docker | Run and passed — see [OpenID conformance](doc/openid-conformance.md) |
| Certification by the OpenID Foundation, security review by someone else | Before use in production — see [Threat model](doc/threat-model.md) and [Reviewer guide](doc/security-review.md) |
| Sign-in across devices by QR code, enterprise SSO (SAML), children's accounts with a guardian, guest accounts, service accounts, audited impersonation and the `act` claim (RFC 8693), back-channel logout, dynamic client registration, pairwise subjects, request objects, SMS as a second factor, passkey-first registration, account deletion | Planned |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle, the firewall, the routes, the tables and the recipes of the kit. |
| [Configuration](doc/configuration.md) | Every option, module by module, with an example. |
| [Accounts and passwords](doc/accounts.md) | Registration, verified addresses, the account page, passwords, their reset and the lock. |
| [Passkeys](doc/passkeys.md) | WebAuthn: the ceremonies, what is checked, the Stimulus controller. |
| [Authenticator application and recovery codes](doc/totp.md) | TOTP, recovery codes and the second step of a sign-in. |
| [Sign-in links and phone numbers](doc/links-and-sms.md) | The `login_link` authenticator and the verification by text message. |
| [Step-up](doc/step-up.md) | `PHIL_STEP_UP` on the sudo mode of Symfony Security 8.2, and the re-authentication page. |
| [Devices and sessions](doc/sessions.md) | Sessions tied to rows, closed from another device; risky sign-ins. |
| [Federated providers](doc/external-providers.md) | The port `ExternalProvider` and the rules that link identities to accounts. |
| [OpenID Connect provider](doc/oidc-provider.md) | Endpoints, clients and how they authenticate — `private_key_jwt` included —, tokens and their families, refresh tokens bound to the session, resources, the `client_credentials` grant, keys and their rotation, consent, logout. |
| [Threat model](doc/threat-model.md) | Assets, trust boundaries, what the bundle defends against and how, open risks. |
| [Reviewer guide](doc/security-review.md) | The scope of the security review, its entry points, and how to run the tests and the suite. |
| [OpenID conformance](doc/openid-conformance.md) | The OpenID Foundation's conformance suite in Docker: how to run it, and its results. |
| [Extension points](doc/extension-points.md) | The services to replace, the tags to add to, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, and the fuzz targets. |
| [Reference](doc/reference.md) | Every class, service, route, table and event of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\IdentityProviderBundle\PhilIdentityProviderBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    site_name: Example
    login_route: app_login
    mailer: { from: accounts@example.com }
    modules: { passkeys: true, totp: true, recovery_codes: true }
    passkeys: { rp_id: example.com, origins: ['https://example.com'] }
    secrets: { kms_key: identity }   # the TOTP secrets, encrypted by symfony/key-management
```

```yaml
# config/routes/phil_identity_provider.yaml
phil_identity_provider:
    resource: .
    type: phil_identity_provider
```

The firewall names the bundle's user provider, user checker and authenticators: see [Installation](doc/installation.md).

## Requirements

PHP 8.5 or later with the `mbstring`, `openssl` and `sodium` extensions, Symfony 8.2 or later, Doctrine ORM 3.7 or
later, `web-token/jwt-library` 4.2 or later, `phil/passwords`, `phil/rate-limit` and `phil/url`. In production, set
`public_origin` or `framework.trusted_hosts`: the bundle refuses to boot without either (see
[Where the links point](doc/accounts.md#where-the-links-point)).

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
