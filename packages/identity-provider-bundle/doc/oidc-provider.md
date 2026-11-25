# OpenID Connect provider

The module `oidc` makes the site an OpenID Connect provider for its clients: other sites and applications sign their
users in with the accounts of this one. It implements the authorization code flow with PKCE, the only flow OAuth 2.0
Security Best Current Practice (RFC 9700) keeps for people, and the `client_credentials` grant for a client that asks
tokens for itself.

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
bin/console phil:identity-provider:client:create worker "A worker" --jwks=config/clients/worker.jwks.json \
    --resource=https://api.example/ --scope=events:read
bin/console phil:identity-provider:client:list
bin/console phil:identity-provider:client:disable app --rotate-secret
bin/console phil:identity-provider:client:keys worker config/clients/worker-next.jwks.json
bin/console phil:identity-provider:client:disable app
```

A confidential client receives a secret of 256 bits, shown once and kept as its SHA-256; it authenticates by HTTP Basic
(`client_secret_basic`) or in the form body (`client_secret_post`). A public client — a single-page or native
application — has no secret and proves itself by PKCE alone (`none`). A redirect address is an absolute `https` address
read by `phil/url`, without a fragment — `http` only on `localhost` with `oidc.allow_localhost` —, kept in its canonical
form, which the client sends exactly: addresses are compared as strings. `--first-party` marks a client of the same
owner, which needs no consent.

A confidential client may register, in place of a secret, the public keys it signs client assertions with
(`--jwks`, a JSON file whose `keys` is a list of JSON Web Keys): it then authenticates by `private_key_jwt` alone and
receives no secret. A client registers the resources it may ask tokens for with `--resource`, read and kept like a
redirect address; a client that only asks tokens for itself registers resources and no redirect address.

Every client sends a PKCE challenge, unless a confidential client is registered with `--pkce-optional`: the Basic
profile of OpenID Connect allows a confidential client without PKCE, which then relies on its secret and on the `nonce`
its ID token carries back. Such a client sends a `nonce` with every request that carries no challenge, or the request
goes back to it with `invalid_request` (RFC 9700, section 2.1.1): without either, nothing would bind the code to the
session that asked for it; `oidc.require_nonce_without_pkce: false` lifts that rule, for a test suite only. A challenge
sent anyway is checked the same way, the `nonce` then optional, and a public client can never go without.

## Client authentication

`oidc.client_authentication.methods` lists the methods the provider accepts, all four by default. A client authenticates
by the one its registration names, never another:

| Method | Client | What it sends |
|---|---|---|
| `client_secret_basic` | with a secret | the identifier and the secret in HTTP Basic, each form-encoded |
| `client_secret_post` | with a secret | `client_id` and `client_secret` in the form body |
| `private_key_jwt` | with keys | `client_assertion_type` set to `urn:ietf:params:oauth:client-assertion-type:jwt-bearer` and `client_assertion` |
| `none` | public | `client_id` alone; PKCE stands for the secret |

A provider that accepts only client assertions and public clients leaves out the two secret methods:

```yaml
phil_identity_provider:
    oidc:
        client_authentication:
            methods: [private_key_jwt, none]
            clock_skew: 60
            assertion_max_lifetime: 300
            legacy_assertion_audiences: false
```

A client assertion (RFC 7523, OpenID Connect Core, section 9) is a JSON Web Token signed with one of the client's keys,
the one its `kid` names, with that key's own algorithm, and read under the bounds of [JSON Web
Signatures](#json-web-signatures):

- `iss` and `sub` are the client's identifier; `client_id`, when sent, must be the same;
- `aud` is the issuer, as a string — `https://id.example.com` —, never an address built from the request
  (draft-ietf-oauth-rfc7523bis): an assertion made for the token endpoint cannot be replayed at another endpoint of the
  provider, nor one made for another server here. A client written for RFC 7523, which names the token endpoint —
  `https://id.example.com/oidc/token` — or puts its audiences in an array, is accepted only with
  `legacy_assertion_audiences: true`, off by default, until it is updated;
- `exp` is required, past by no more than `clock_skew`, and no further ahead than `assertion_max_lifetime`; `iat` and
  `nbf`, when present, are ahead by no more than `clock_skew`;
- `jti` is required, at most 256 bytes, and used once: the provider records the SHA-256 of the client and the `jti` in
  `phil_identity_assertion` until the assertion expires, the skew added, and refuses the same assertion again — the key
  of the row refuses the second of two requests that present it at the same time. `phil:identity-provider:purge`
  removes the expired rows.

Every refusal is `invalid_client`, without saying which check failed. Another method sent beside the assertion is
`invalid_request`. The keys of a client are public JSON Web Keys only — RSA of at least 2048 bits, P-256 or Ed25519,
each with its `kid` and `alg`, at most five —; `phil:identity-provider:client:keys` replaces them for a rotation:
publish the next key beside the current one, let the client switch, then keep the next key alone.

## The authorization request

`AuthorizationRequestParser` reads the request, in this order:

1. an unknown or disabled client, or a redirect address it did not register exactly, is shown to the person, never
   redirected, so that the provider cannot send people anywhere;
2. a parameter given twice, a request object (`request_not_supported`, `request_uri_not_supported`), a response type
   other than `code`, a response mode other than `query`, no `openid` scope, a PKCE challenge that is not `S256`, out of
   shape, or missing when the client requires one, no `nonce` in a request without a PKCE challenge (RFC 9700,
   section 2.1.1), a `prompt` other than `none`, `login` and `consent` — the provider
   shows no account chooser, so `select_account` is refused rather than ignored —, `prompt=none` with another value, an
   out-of-shape `max_age` go back to the client with the error, the state and the issuer (RFC 9207);
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
added afterwards (RFC 9700, section 2.1.1); a second exchange revokes every token issued from it. The code is marked
used by one conditional write, so that of two requests presenting it at the same time only one gets tokens; the other
is refused as a reuse. The answer holds:

- an access token, a JSON Web Token of type `at+jwt` (RFC 9068) whose `jti` has a row, so that it can be revoked and
  introspected; its audience is the issuer, for userinfo;
- an ID token with `iss`, `sub`, `aud`, `exp`, `iat`, `auth_time`, `nonce`, `amr` — the methods proven —, `sid` and
  `at_hash`; the claims of the scopes come from userinfo;
- with the scope `offline_access`, an opaque refresh token, rotated at each use by one conditional write: a rotated
  token presented again — even by a request that read it at the same time as the one that rotated it — revokes its
  whole family, the sign of a stolen token. [Token families](#token-families) says what a family is.

### Token families

Every token issued from one authorization code — each access token and each refresh token, rotation after rotation —
shares one family, a ULID kept on each row. The family is what the provider revokes when something
is wrong:

- a code exchanged twice revokes the family of its first exchange;
- a refresh token presented after its rotation — by the client that rotated it, or by whoever stole it — revokes the
  family, and the event `identity.oidc.token_reuse` is journaled;
- a refresh token revoked by the client (RFC 7009) takes its family with it;
- the end of the session the family was issued in revokes it, as [Refresh tokens and the
  session](#refresh-tokens-and-the-session) says.

The rotation is one conditional `UPDATE … WHERE rotated_at IS NULL AND revoked_at IS NULL`, whose count of rows names
the one request that rotated the token: of two refreshes that read the same token at the same time, one gets new tokens
and the other is refused as a reuse, which revokes the family — the winner's new tokens with it. The tests play both
orders with two requests interleaved (`ConcurrentUseTest`) and with two database connections (`tests/Integration`).

### Refresh tokens and the session

With `oidc.refresh_token_bound_to_session`, on by default, a refresh token issued in a session lives no longer than it:

- once the session is closed — signed out, closed from another device, or purged —, the refresh token is refused with
  `invalid_grant` and its family revoked;
- a refresh touches its session, as a request of its browser does: past `sessions.idle_timeout` without activity, or
  `sessions.max_age` after it opened, the session is closed there and then — its tokens revoked, the clients told
  through back-channel logout, the event `identity.session.closed` written with the reason — and the refresh is
  refused with `invalid_grant`; a session never touched again cannot go on issuing tokens through refresh;
- within those bounds, a refresh counts as the activity of the session, so that a client in use keeps it open past
  `sessions.idle_timeout`; nothing keeps it open past `sessions.max_age`;
- a refresh token never expires later than `sessions.max_age` after the session opened, nor later than
  `oidc.session_max_age` when it is set, and is refused past either; a code exchanged past them gives no refresh token.

A refresh token issued without a session, and every refresh token when the option is off, keeps
`oidc.refresh_token_lifetime`. The module `sessions` holds the sessions: without it, nothing binds the tokens.

### Resources and the audience

A token request may name a `resource` (RFC 8707) the client registered, compared in its canonical form: the access
token is for it, its `aud`, and introspection answers it as `aud`. A token for a resource no longer reads userinfo,
whose audience is the issuer. One resource per request: several, or one the client did not register, is
`invalid_target`.

### Tokens for a client itself

A confidential client — with a secret or keys — that registered resources asks a token for itself by the
`client_credentials` grant (RFC 6749, section 4.4):

```http
POST /oidc/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&resource=https%3A%2F%2Fapi.example%2F&scope=events%3Aread
&client_assertion_type=urn%3Aietf%3Aparams%3Aoauth%3Aclient-assertion-type%3Ajwt-bearer&client_assertion=eyJ…
```

The access token is for the resource named, or the client's only one — several and none named is `invalid_target` —;
its `sub` and `client_id` are the client; its scopes are those asked, among the client's own, the scopes about an
account (`openid`, `profile`, `email`, `phone`, `offline_access`) left out — all of them when none is asked. No ID
token and no refresh token come with it, and userinfo refuses it. A public client, or one without resources, gets
`unauthorized_client`. The scopes of resources are declared in `oidc.extra_scopes`, so that clients may register them.

### Optional claims of the ID token

| Option | Claim |
|---|---|
| `oidc.id_token.acr` | `acr`, the same value in every ID token, such as a level the clients agreed on |
| `oidc.id_token.idp` | `idp`, the identity provider every ID token names |
| `oidc.id_token.session_start` | `session_start`, when the session of the ID token opened, as a NumericDate; left out without a session |

The discovery document lists the claims turned on, and `acr_values_supported` names the `acr`.

Introspection answers `active: false` for a token that is not the asking client's, expired, revoked or rotated, and for
every token of an account that was removed or disabled, as userinfo refuses them. Userinfo also refuses the tokens of
a client that was disabled or removed.

The subject follows `oidc.subject.type`; a token of the `client_credentials` grant has the client as its subject.

| Type | `sub` |
|---|---|
| `account`, the default | the account's ULID, the same for every client |
| `public` | an opaque identifier, the same for every client: the HMAC-SHA256 of the account's ULID under `oidc.subject.salt`, in base64url |
| `pairwise` | an opaque identifier per sector: the HMAC-SHA256 of the client's sector and the account's ULID under the salt, so that clients of different sectors cannot match their people by it (OpenID Connect Core, section 8.1) |

The sector of a client is the host `--sector-identifier` names — shared by the clients of one owner, which then see the
same subject —, or the host of its redirect addresses when they all share one, or else its own identifier. The salt,
32 bytes at least, comes from the secrets vault and never changes once clients have seen subjects: another salt gives
every account another subject. The ULID stays internal with the opaque types; the ID token, the access token,
introspection, userinfo, the logout token and the check of an `id_token_hint` all use the subject of the client.
Changing the type changes every subject the clients know. Userinfo takes the access token in the `Authorization`
header or, by `POST`, in the `access_token` member of a form body (RFC 6750), never in the query and never both ways.

## JSON Web Signatures

Every token the provider reads back — its own and the client assertions — goes through `web-token/jwt-library`:
`CompactSerializer` reads the compact form and `JWSVerifier` verifies it. `Application\Jose\Jws` bounds a token before
the library sees it — at most 8 KiB, three parts, a header and claims that are JSON objects of bounded depth, a header
naming an algorithm and a key, no `crit` and no `b64` —, then verifies it with the one algorithm of the key its `kid`
names; `none` and the HMAC algorithms are never registered.

`Jws::encode()` writes a token with an `Application\Contract\Signer`: the header — `alg`, `kid`, the members given and
`typ` —, the claims, and the signature the signer gives over both. `Application\Jose\PrivateKey`, the signer of the
database and of key files, holds a key as a JSON Web Key of the library — an RSA key of 3072 bits, created by
`JWKFactory`, a P-256 key or an Ed25519 key — and loads it once, when it is made: an OpenSSL key for RS256 and ES256,
the libsodium secret key for EdDSA. Each signature then costs the signature only.

`tests/Benchmark/JwsBench.php` measures each algorithm. On a recent laptop, an RS256 signature takes about 2.2 ms with
the loaded key, against 3.6 ms when the library rebuilt the PEM of the key at each signature, and ES256 about 33 µs
against 90 µs; EdDSA takes about 24 µs. An RSA signature of 3072 bits stays the slowest by far: choose `ES256` or
`EdDSA` when the clients support them.

## Scopes and claims

| Scope | Claims |
|---|---|
| `openid` | `sub` |
| `email` | `email`, `email_verified` |
| `profile` | `name` |
| `phone` | `phone_number`, `phone_number_verified` |
| `offline_access` | a refresh token |

More claims come from the application: a service implementing `Application\Contract\ClaimsProvider` — autoconfigured,
or tagged `phil_identity_provider.claims_provider` — receives the account, the scopes of the access token and its client,
and answers the claims those scopes grant, such as `locale` for `profile`. Userinfo adds them after the claims of the
scopes, provider after provider. A provider that answers a claim the provider sets — `sub`, `email`, `acr` and the
others of `UserClaims::RESERVED` — or one an earlier provider answered fails the request with a `LogicException`:
one claim never stands for another.

```php
use Phil\IdentityProviderBundle\Application\Contract\ClaimsProvider;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

final readonly class LocaleClaims implements ClaimsProvider
{
    public function claims(Account $account, array $scopes, string $clientId): array
    {
        return \in_array('profile', $scopes, true) ? ['locale' => 'fr-CA'] : [];
    }
}
```

## Keys and their rotation

The keys come from the source `oidc.signing_keys.source` names, through the port `Application\Contract\SigningKeySource`:
the key that signs, the key set the discovery document publishes, and the public key of a `kid`. A source keeps the
keys it loaded: the private key of RS256, the default algorithm, is decrypted and turned into an OpenSSL key once per
process, not at each signature (see [JSON Web Signatures](#json-web-signatures)).

| Source | Where the keys are | Rotation |
|---|---|---|
| `database`, the default | `phil_identity_signing_key`, sealed under `secrets.kms_key` | By `phil:identity-provider:rotate-keys` |
| `file` | The files of `oidc.signing_keys.files`, sealed under `secrets.kms_key` with `sealed: true` | By a deployment |
| `service` | The service of `oidc.signing_keys.service`, such as the adapter of a hardware security module | By the module |

### The database

`SigningKeys` keeps the keys in `phil_identity_signing_key`, their private JSON Web Key sealed by the `SecretCipher`. A
provider without a key creates and activates one at its first token. `phil:identity-provider:rotate-keys`, run daily,
removes the keys retired for longer than `oidc.retired_key_retention`, activates the pending key once it has been
published for `oidc.key_activation_delay` — retiring the active one —, and creates the next pending key. Clients that
cache the key set see each key before it signs, and keep it until the last token it signed has expired. After a key
was exposed, `--now` activates the pending key at once.

With `oidc.signing_keys.automatic_rotation: false`, a run of the command on the schedule changes nothing and says so,
for a provider whose operators rotate by hand: `--force` rotates, `--now` rotates and activates at once.

The first key and each rotation are made under one lock, `phil_identity_provider.signing_keys`, taken from the
`LockFactory` of `oidc.lock_factory` — `lock.factory` by default —, the keys read again once it is held: instances that
start, or rotate, at the same time make one key, not one each. The lock's store must be shared by every server: a
`flock` store guards one server only. Every read of the keys still settles what an unshared store, or keys written
before, may have left, the same way on every instance: of several active keys, all but the one activated last — the
greatest `kid` breaking a tie — are retired, and stay published for the tokens they signed; of several pending keys,
all but the oldest are removed, having signed nothing. The settlement is journaled as `identity.oidc.key_rotated`,
with the keys retired and the count removed.

The key that signs is kept in `LoadedKeys` under its `kid`, which names one key for its whole life: a rotation names
another key, loaded in turn, and at most four keys are kept.

### Key files

```yaml
phil_identity_provider:
    oidc:
        algorithm: RS256
        signing_keys:
            source: file
            sealed: true
            files:
                - '%kernel.project_dir%/config/keys/current.sealed'
                - { path: '%kernel.project_dir%/config/keys/previous.sealed', kid: '2026-10' }
```

`FileSigningKeys` reads each file once per process, at the first token signed or read: a PEM, a private JSON Web Key,
or, with `sealed: true`, either sealed under `secrets.kms_key` by `phil:identity-provider:seal-key <file>`, which
checks the key and prints the sealed text. The first file signs; the others are published, for the tokens they signed.
A key's `kid` is the one given, or else the JWK thumbprint of its public key (RFC 7638). A file larger than 64 KiB, a
key of another algorithm, or two files with one `kid` are refused at the first use. The bundle never rotates these
keys: put the new key first, keep the previous one after it while its tokens may still be presented, then remove it.

### A hardware security module

A key that never leaves a hardware security module, or a key management service that signs, is an adapter of the
application: a service implementing `SigningKeySource`, whose `active()` gives an `Application\Contract\Signer` — its
`algorithm`, its `kid`, and `sign()`, which sends the JWS signing input to the module and gives back the signature as
JWA writes it (for ES256, `r` and `s` of 32 bytes each, not the DER of a signature) — and whose `jwks()` and
`publicKey()` publish the public keys. Name it in `oidc.signing_keys.service`; the rotation is the module's.

## Consent and logout

The consent page names the client and each scope in words; the consent covers the scopes granted, and a request for
more asks again. The account withdraws it from the page of its sessions, which revokes the client's tokens.

`/oidc/logout` with an `id_token_hint` issued for the account signed in signs out at once; without one, it asks the
person to confirm, so that a link cannot sign anyone out. It then goes back to the `post_logout_redirect_uri` the
client registered, with the state, or shows that the person is signed out.

### Back-channel logout

With `oidc.backchannel_logout.enabled`, the provider tells the clients that a session ended (OpenID Connect
Back-Channel Logout 1.0). A client registers the address it listens on:

```sh
bin/console phil:identity-provider:client:create app "The App" --redirect-uri=https://app.example/callback \
    --backchannel-logout-uri=https://app.example/backchannel-logout
```

Whenever a session closes — signed out here or by RP-initiated logout, closed from another device, or closed with every
other session after a password change —, each client the provider issued tokens to in it receives one
`Infrastructure\Messenger\SendLogoutToken` on the bus of `oidc.backchannel_logout.bus`, carrying the identifiers of
the client, the account and the session, nothing more. Its handler signs a logout token when it runs — a JSON Web Token
of type `logout+jwt`, `iss` the provider, `aud` the client, `sub` the account, `sid` the session the ID token named,
`iat`, an `exp` two minutes later, a new `jti`, the event `http://schemas.openid.net/event/backchannel-logout` and no
`nonce` — and posts it as `logout_token` in a form body to the client's address, following no redirect, within
`oidc.backchannel_logout.timeout` seconds. An answer other than `200` or `204`, or none, throws `DeliveryFailed`, and
Messenger tries again by the retry strategy of the transport; a client removed, disabled, or without an address is
skipped. Route the message to an asynchronous transport, so that signing out never waits on a client:

```yaml
framework:
    messenger:
        transports:
            identity: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            Phil\IdentityProviderBundle\Infrastructure\Messenger\SendLogoutToken: identity
```

The discovery document then announces `backchannel_logout_supported` and `backchannel_logout_session_supported`.

## Not implemented

Dynamic client registration, request objects, the claims parameter, front-channel logout,
token exchange and the `act` claim (RFC 8693), a key set fetched from a client's `jwks_uri`, mutual TLS, and DPoP are
planned.
