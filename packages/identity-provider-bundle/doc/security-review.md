# Reviewer guide

For the person who reviews the bundle's security before it is used in production. Read the
[threat model](threat-model.md) first: it names the assets, the trust boundaries, the defences and the open risks this
review checks.

## Scope

| In scope | Where |
|---|---|
| JSON Web Signatures and the provider's keys, on `web-token/jwt-library` | `src/Application/Jose`, `src/Domain/Jose` (the algorithms) |
| The OpenID Connect protocol: authorization request, PKCE, scopes | `src/Domain/Oidc` |
| Codes, tokens, refresh rotation, revocation, introspection, keys and their rotation, clients | `src/Application/Oidc` |
| The provider's endpoints | `src/Infrastructure/Http/Oidc` |
| WebAuthn: CBOR, authenticator data, client data, COSE keys, signatures | `src/Domain/WebAuthn` |
| Firewall pieces: authenticators, second step, step-up, sessions | `src/Infrastructure/Security` |
| Accounts, passwords, links, lock | `src/Application`, `src/Domain/Security`, `src/Domain/Totp` |

Out of scope: what [the threat model](threat-model.md#what-the-application-keeps) leaves to the application — rate
limits, transport security, headers, the secrets vault, the database —, and `web-token/jwt-library` itself beyond how
the bundle calls it.

## Entry points

Untrusted input reaches the bundle at these places; each row names the first code that reads it.

| Input | Route | First reader |
|---|---|---|
| Authorization request (query or form) | `/oidc/authorize` | `Domain\Oidc\AuthorizationRequestParser::parse()` |
| Consent answer | `/oidc/consent` | `AuthorizeController::consent()`, CSRF checked first |
| Token request, client credentials | `/oidc/token` | `ClientCredentials::from()`, then `Tokens::authenticateClient()` |
| Access token (header or form body) | `/oidc/userinfo` | `UserinfoController`, then `Tokens::bearer()` and `Jws::decode()` |
| Token to revoke or introspect | `/oidc/revoke`, `/oidc/introspect` | `Tokens::revoke()`, `Tokens::introspect()` after client authentication |
| `id_token_hint` | `/oidc/logout` | `Tokens::idTokenHint()`, then `Jws::decode()` |
| Passkey registration and assertion | `/account/passkeys/register`, the passkey sign-in | `Domain\WebAuthn\RelyingParty`, `Cbor` |
| One-time codes | the second step, re-authentication | `OneTimeCodeAuthenticator`, `Totp`, `RecoveryCodes` |
| Signed links | `/verify-email`, `/reset-password`, sign-in links | `SignedLinks`, the `login_link` authenticator on a POST only (`LoginLinkPostOnlyPass`) |

Where to look first in the JOSE layer:

- `Jws::decode()`: the bounds before the library, the `kid` lookup, the algorithm that must be the key's own, the
  `AlgorithmManager` holding that algorithm alone, every library exception turned into a refusal;
- `PrivateKey::of()`: the checks on a key read back from the store — type, curve, private members, RSA size;
- `SigningKeys::publicKey()` and `jwks()`: only published keys verify, retired ones until their retention ends;
- `Tokens::exchangeCode()` and `AuthorizationRequestParser`: PKCE required per client, and no verifier accepted for a
  code issued without a challenge.

## Running the tests

In the monorepo:

```sh
composer install
vendor/bin/phpunit -c phpunit.dist.xml packages/identity-provider-bundle/tests   # unit and functional suites
composer test:next                     # the tests on the next PHP, in Docker
composer phpstan                       # PHPStan at the maximum level
composer fuzz                          # the fuzz targets, among them jwt and authorization-request
composer infection                     # mutation testing
vendor-bin/phpbench/vendor/bin/phpbench run packages/identity-provider-bundle/tests/Benchmark --report=phil
```

The fuzz targets and their seeds are listed in [Testing](testing.md#fuzzing).

## Running the conformance suite

```sh
packages/identity-provider-bundle/tests/Conformance/run.sh
```

Docker starts the OpenID Foundation's suite at a pinned release and the provider behind https, prepares a test account
and two clients, and plays the Basic OP and Config OP plans; [OpenID conformance](openid-conformance.md) explains the
setup, how to watch the run at `https://localhost.emobix.co.uk:8443`, and the results to compare with.

## Reporting

File what you find privately, as the [security policy](../SECURITY.md) explains, never in a public issue. Name the file
and line, the input that shows it, and the row of the threat model it breaks.
