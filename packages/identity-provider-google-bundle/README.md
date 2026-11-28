# phil/identity-provider-google-bundle

**Let people sign in with Google to an application running phil/identity-provider-bundle, and link their Google
account to the account they already have — never merged on an address Google does not vouch for — while Google's
Cross-Account Protection closes their sessions when their Google account is revoked, disabled or deleted.**

phil/identity-provider-google-bundle is Google as a federated provider of phil/identity-provider-bundle. It plugs
into the bundle's module `external`: `/login/with/google` sends the person to Google, and the bundle's callback reads
them back through this package, then signs in, links or creates the account by the bundle's own rules.

The OpenID Connect protocol is Symfony Security's: its discovery of Google's endpoints, its client for the token
endpoint, its check of the ID token's signature against Google's keys, cached, and of its claims. The package adds
what a sign-in with Google needs around them:

- the **authorization request**: a state, a nonce and a PKCE challenge (S256) kept in the session, several tabs at a
  time, each answer accepted once;
- **minimal data**: the scopes `openid` and `email` only, the name with `profile` when the configuration asks for it,
  no refresh token, and no token kept once the identity is read;
- **safe linking**: an address is handed to the bundle as verified only when Google is its authority —
  `email_verified` and a `gmail.com` address, or a Google Workspace account (`hd`) on its own domain —, so the bundle
  never links a Google account to an existing account on any other address; the accepted Workspace domains, when
  configured, are checked on the `hd` claim;
- a **receiver of security events** (RFC 8935): Google's Cross-Account Protection (RISC) posts signed tokens when a
  Google account's sessions or tokens are revoked, or the account is disabled or deleted; the sessions of the linked
  account are closed, and an account disabled as hijacked may not sign in with Google until Google enables it again.
  The receiver also reads the session revocation of the Shared Signals Framework, for other providers.

Every request to Google goes through phil/safe-http: addresses checked, private networks refused, no redirect, time and
size bounded.

## Status

| Part | State |
|---|---|
| `GoogleProvider` and `GoogleSignIn`: authorization code flow with state, nonce and PKCE, on Symfony's OpenID Connect pieces | Implemented and tested |
| `GoogleClaims`: subject, address trusted on Google's authority, accepted Workspace domains, name on request | Implemented and tested |
| `SafeHttpTransport`: Symfony's HTTP client contract over phil/safe-http | Implemented and tested |
| `SecurityEventReceiver` and the RISC endpoint: signed tokens, replay guard, sessions closed, hijacked accounts held | Implemented, tested and fuzzed |
| Configuration and Flex recipe | Implemented and tested |
| Registering the RISC stream at Google from a command | Not written: see [Cross-Account Protection](doc/risc.md) |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the Google Cloud client, the bundle, the routes and the firewall. |
| [Configuration](doc/configuration.md) | Every option, with its default. |
| [Linking accounts](doc/linking.md) | Sign-in, linking and unlinking, which address is trusted, the data asked for. |
| [Cross-Account Protection](doc/risc.md) | The receiver of Google's security events, what each event does, registering the stream. |
| [Extension points](doc/extension-points.md) | The ports, the event and the services an application replaces. |
| [Testing](doc/testing.md) | The tests, the local Google and the fuzz target. |
| [Reference](doc/reference.md) | Every public type, route and service. |

## Quick start

```sh
composer require phil/identity-provider-google-bundle
php bin/console secrets:set GOOGLE_CLIENT_SECRET
```

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    modules:
        external: true

# config/packages/phil_identity_provider_google.yaml
phil_identity_provider_google:
    client_id: '%env(GOOGLE_CLIENT_ID)%'
    client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
```

A "Link with Google" button then appears on the bundle's page of linked identities, and `/login/with/google` signs a
person in.

## Requirements

PHP 8.5 or later with `ext-mbstring`, `ext-openssl` and `ext-sodium`, Symfony 8.2, phil/identity-provider-bundle with its module `external`, and
`web-token/jwt-library`, which Symfony's OpenID Connect pieces verify the tokens with.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
