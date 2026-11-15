# OpenID Connect provider

The module `oidc` makes the site an OpenID Connect provider for its clients: other sites and applications sign their
users in with the accounts of this one. It implements the authorization code flow with PKCE, the only flow OAuth 2.0
Security Best Current Practice (RFC 9700) keeps.

```yaml
phil_identity_provider:
    modules: { oidc: true }
    oidc:
        issuer: 'https://id.example.com'
        algorithm: RS256
```

## Endpoints

| Endpoint | Route | Method |
|---|---|---|
| Discovery (OpenID Connect Discovery 1.0, RFC 8414) | `/.well-known/openid-configuration` | `GET` |
| Key set | `/oidc/jwks` | `GET` |
| Authorization | `/oidc/authorize` | `GET`, `POST` |
| Consent | `/oidc/consent` | `POST` |
| Token (RFC 6749) | `/oidc/token` | `POST` |
| Userinfo | `/oidc/userinfo` | `GET`, `POST` |
| Revocation (RFC 7009) | `/oidc/revoke` | `POST` |
| Introspection (RFC 7662) | `/oidc/introspect` | `POST` |
| RP-initiated logout | `/oidc/logout` | `GET`, `POST` |

The discovery document and the key set are public and cacheable for five minutes; every other answer is
`Cache-Control: no-store`.

## Clients

Clients are registered in `phil_identity_client` by the console:

```sh
bin/console phil:identity-provider:client:create app "The App" \
    --redirect-uri=https://app.example/callback \
    --post-logout-redirect-uri=https://app.example/ \
    --scope=email --scope=offline_access
bin/console phil:identity-provider:client:create spa "The SPA" --redirect-uri=https://spa.example/cb --public
bin/console phil:identity-provider:client:create server "A server" --redirect-uri=https://server.example/cb --pkce-optional
bin/console phil:identity-provider:client:list
bin/console phil:identity-provider:client:disable app --rotate-secret
bin/console phil:identity-provider:client:disable app
```

A confidential client receives a secret of 256 bits, shown once and kept as its SHA-256; it authenticates by HTTP Basic
(`client_secret_basic`) or in the form body (`client_secret_post`). A public client — a single-page or native
application — has no secret and proves itself by PKCE alone (`none`). A redirect address is an absolute `https` address
read by `phil/url`, without a fragment — `http` only on `localhost` with `oidc.allow_localhost` —, kept in its canonical
form, which the client sends exactly: addresses are compared as strings. `--first-party` marks a client of the same
owner, which needs no consent.

Every client sends a PKCE challenge, unless a confidential client is registered with `--pkce-optional`: the Basic
profile of OpenID Connect allows a confidential client without PKCE, which then relies on its secret and on the `nonce`
its ID token carries back. A challenge sent anyway is checked the same way, and a public client can never go without.

## The authorization request

`AuthorizationRequestParser` reads the request, in this order:

1. an unknown or disabled client, or a redirect address it did not register exactly, is shown to the person, never
   redirected, so that the provider cannot send people anywhere;
2. a parameter given twice, a request object (`request_not_supported`, `request_uri_not_supported`), a response type
   other than `code`, a response mode other than `query`, no `openid` scope, a PKCE challenge that is not `S256`, out of
   shape, or missing when the client requires one, `prompt=none` with another value, an out-of-shape `max_age` go back
   to the client with the error, the state and the issuer (RFC 9207);
3. scopes the provider or the client does not know are dropped.

Then: nobody signed in, the firewall asks them to sign in and comes back — `prompt=none` answers `login_required`;
`prompt=login`, or an authentication older than `max_age`, sends them to the re-authentication page first. Before
either, the request is kept as the firewall's target path, in its `GET` form, so that every way of signing in — the
application's own form included, and after a request sent by `POST` — comes back to it; a consent
missing for the scopes shows the consent page — `prompt=none` answers `consent_required`; a code goes back with the
state and `iss`.

## Tokens

The code lives `oidc.code_lifetime` seconds and is exchanged once, by its client, with the same redirect address and the
PKCE verifier of its challenge — and without a verifier when the request carried no challenge, so that one cannot be
added afterwards (RFC 9700, section 2.1.1); a second exchange revokes every token issued from it. The answer holds:

- an access token, a JSON Web Token of type `at+jwt` (RFC 9068) whose `jti` has a row, so that it can be revoked and
  introspected; its audience is the issuer, for userinfo;
- an ID token with `iss`, `sub`, `aud`, `exp`, `iat`, `auth_time`, `nonce`, `amr` — the methods proven —, `sid` and
  `at_hash`; the claims of the scopes come from userinfo;
- with the scope `offline_access`, an opaque refresh token, rotated at each use: a rotated token presented again
  revokes its whole family, the sign of a stolen token.

The subject is the account's ULID, the same for every client. Userinfo takes the access token in the `Authorization`
header or, by `POST`, in the `access_token` member of a form body (RFC 6750), never in the query and never both ways.

## JSON Web Signatures

Every token the provider signs, and every token it reads back, goes through `web-token/jwt-library`: `JWKFactory`
creates the keys, `JWSBuilder` signs, `JWSVerifier` verifies, `CompactSerializer` writes and reads the compact form.
`Application\Jose\Jws` bounds a token before the library sees it — at most 8 KiB, three parts, a header and claims
that are JSON objects of bounded depth, a header naming an algorithm and a key, no `crit` and no `b64` —, then
verifies it with the one algorithm of the key its `kid` names; `none` and the HMAC algorithms are never registered.
`Application\Jose\PrivateKey` holds a key as a JSON Web Key of the library: an RSA key of 3072 bits, a P-256 key or
an Ed25519 key.

`tests/Benchmark/JwsBench.php` measures each algorithm. The library rebuilds the PEM of an RSA key at each signature,
which makes RS256 the slowest by far — milliseconds where ES256 and EdDSA take tens of microseconds —: choose `ES256` or
`EdDSA` when the clients support them.

## Scopes and claims

| Scope | Claims |
|---|---|
| `openid` | `sub` |
| `email` | `email`, `email_verified` |
| `profile` | `name` |
| `phone` | `phone_number`, `phone_number_verified` |
| `offline_access` | a refresh token |

## Keys and their rotation

`SigningKeys` keeps the keys in `phil_identity_signing_key`, their private JSON Web Key sealed by the `SecretCipher`. A
provider without a key creates and activates one at its first token. `phil:identity-provider:rotate-keys`, run daily,
removes the keys retired for longer than `oidc.retired_key_retention`, activates the pending key once it has been
published for `oidc.key_activation_delay` — retiring the active one —, and creates the next pending key. Clients that
cache the key set see each key before it signs, and keep it until the last token it signed has expired. After a key
was exposed, `--now` activates the pending key at once.

## Consent and logout

The consent page names the client and each scope in words; the consent covers the scopes granted, and a request for
more asks again. The account withdraws it from the page of its sessions, which revokes the client's tokens.

`/oidc/logout` with an `id_token_hint` issued for the account signed in signs out at once; without one, it asks the
person to confirm, so that a link cannot sign anyone out. It then goes back to the `post_logout_redirect_uri` the
client registered, with the state, or shows that the person is signed out.

## Not implemented

Dynamic client registration, request objects, pairwise subjects, the claims parameter, front- and back-channel logout,
token exchange and the `act` claim (RFC 8693), and DPoP are planned.
