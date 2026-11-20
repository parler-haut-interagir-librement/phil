# Threat model

What the bundle defends against, how, and what it leaves to the application. A security review by someone who did not
write the code comes before the bundle is used in production, against this page and the
[reviewer guide](security-review.md).

## Assets

| Asset | Where it lives | What protects it |
|---|---|---|
| Password hashes | `phil_identity_account` | Argon2id; never logged, never in an event. |
| Passkey public keys and counters | `phil_identity_passkey` | Public by nature; the counter must move forward. |
| TOTP secrets, recovery code hashes | `phil_identity_totp`, `phil_identity_recovery_codes` | Sealed by the `SecretCipher` (KeyManagement component): the module refuses to start without a key unless `secrets.allow_plaintext_secrets` allows clear; codes keyed with `secret`. |
| Sessions | `phil_identity_session` and the PHP session | Rows closed remotely; Symfony Security migrates the session at sign-in. |
| The provider's private signing keys | `phil_identity_signing_key` | The private JSON Web Key sealed by the `SecretCipher`, bound to its `kid`; it never leaves the server. |
| Codes, access and refresh tokens | `phil_identity_code`, `phil_identity_token` | Only their SHA-256 or their `jti` is kept; short lives; rotation with reuse detection. |
| Client secrets | `phil_identity_client` | 256 random bits, kept as their SHA-256, compared in constant time. |
| The link between people and federated identities | `phil_identity_linked` | Never merged on an address the provider did not verify. |

## Trust boundaries

1. **The browser to the bundle's pages and the authorization endpoint.** Everything in a query, a form or a cookie is
   untrusted: parameters are bounded (`AuthorizationRequestParser::MAX_PARAMETER`), forms carry CSRF tokens, the
   redirect address is checked before anything is sent back.
2. **Clients to the token, userinfo, revocation and introspection endpoints.** A client is authenticated before its
   request is read; a bearer token is a JSON Web Token whose signature, issuer and row are all checked.
3. **Authenticators to the WebAuthn ceremonies.** CBOR, authenticator data, client data and COSE keys are read by
   bounded readers, before any cryptography.
4. **The bundle to `web-token/jwt-library`.** Every JSON Web Signature the provider writes or reads goes through the
   library: `JWKFactory` creates the keys, `JWSBuilder` signs, `JWSVerifier` verifies with an `AlgorithmManager` that
   holds the one algorithm of the key, `CompactSerializer` reads and writes the compact form. The bundle bounds untrusted
   input before the library sees it (see "Token forgery" below) and registers neither `none` nor any HMAC algorithm.
5. **The bundle to OpenSSL and libsodium.** The library signs with OpenSSL (RS256, ES256) and libsodium (EdDSA); the
   signatures of passkeys are verified by `Domain\WebAuthn\PublicKey` on the same extensions.
6. **The bundle to the database and the KeyManagement component.** The database is trusted for integrity, not for the
   secrecy of what the `SecretCipher` seals.

## Credential attacks

| Threat | Defence |
|---|---|
| Guessing passwords online | The firewall's `login_throttling` per address and per identifier and address; with `login_limiter`, per identifier whatever the address too, against guessing spread over many addresses; the progressive lock per account, counted under a lock of its row and checked before the password; passkeys still sign a locked account in. |
| Holding an account's sign-ins back | The count per identifier and the progressive lock can be spent by anyone who knows an address; the lock refuses the password only, the count lasts its interval, and `login_limiter.account_max_attempts` is kept well above the other limits. |
| Choosing one's own address | The addresses counted are the ones Symfony sees: `framework.trusted_proxies` and `framework.trusted_headers` name the proxies only, so that no client sets its address by a header. |
| Weak or breached passwords | `phil/passwords`: strength estimator with the account's own data as first guesses; Pwned Passwords by k-anonymity when turned on. |
| Stolen password database | Argon2id; no password in a log, a message or an event. |
| Phishing | Passkeys: the browser signs only for the relying party; origins checked. |
| Replayed TOTP code | The counter accepted is kept, read again and held before it is saved; a code verifies once, even presented by two requests at the same time. |
| Cloned authenticator | The signature counter must move forward when the authenticator keeps one. |
| Malformed authenticator answers | Bounded CBOR reader: depth, items and lengths checked before allocation; fuzzed. |
| Account enumeration | Registration, password reset and sign-in links answer the same for known and unknown addresses; the registration limit per address counts known and unknown addresses alike. |
| A link that points to another host | The links of the emails are built on `public_origin`, never on the `Host` header of the request; without it, `framework.trusted_hosts` refuses a forged host, and in the `prod` environment the bundle refuses to boot when neither is set. |
| Mass registration | Registration counted per client address and per address typed, and one verification email per address in each `resend_delay`, by default. |
| Guessing the code of the second step | Five wrong codes in a row sign the session out: the next guesses need the password again, under the firewall's limits and the progressive lock. |
| Reused links | Links signed over the properties they change — the address and its verification, the password hash and its date —, so each works once with nothing stored; a reset link sets its password under a lock of the account's row, so two requests at once cannot both use it. |
| A link opened by a mail scanner | `/verify-email` only shows a page that asks to confirm; the address is verified by the form it posts, with a CSRF token. |

## Session attacks

| Threat | Defence |
|---|---|
| Session fixation | Symfony Security migrates the session at sign-in. |
| A stolen device | Sessions closed from another device take effect at the next request; tokens issued in them are revoked, then the session closed, in one transaction. |
| A stolen session after a password change | A new or reset password closes the other sessions and their tokens; `AccountUser::isEqualTo()` compares the password hash, so a session signed in before the change ends at its next request. |
| A session opened long ago used for a sensitive action | `PHIL_STEP_UP` asks for a recent proof of the strongest method. |
| A remembered session answering `max_age` | A session that holds no proof, such as one restored by `remember_me`, signs in again when a client asks for `max_age`. |
| A second factor skipped | The second step is enforced on every request until a strong proof is on the token; only routes named exactly stay open — the challenge, its code, the sign-in with a passkey and `open_routes` —, no prefix. |
| Cross-site request forgery | Every form and script request carries a CSRF token; logout from a link asks to confirm. |

## Provider attacks

| Threat | Defence |
|---|---|
| Open redirect, code theft through a redirect address | Exact match with a registered address, read by `phil/url`; errors about the client shown, not redirected. |
| Code interception or injection | PKCE S256 required of every public client and, by default, of every confidential one; a code issued without a challenge refuses a verifier at the token endpoint (RFC 9700, section 2.1.1); codes hashed, short-lived, used once, reuse revoking the family. |
| Refresh token theft | Rotation by one conditional write, with reuse detection revoking the family, even between two requests at the same time; a refresh token bound to its session by default, refused once the session ends. See [Token families](#token-families). |
| Client impersonation by a stolen secret | `private_key_jwt`: the client keeps a private key the provider never sees; `oidc.client_authentication.methods` can refuse secrets altogether. |
| A client assertion replayed | `jti` recorded until the assertion expires, the skew added, under a key that refuses the second of two requests at once; `exp` required and at most `assertion_max_lifetime` ahead; `aud` the issuer or the token endpoint at the issuer's origin, never an address built from the `Host` header. |
| A token used at another resource | A token names its resource in `aud`, a resource the client registered; userinfo refuses a token of another audience and every token of the `client_credentials` grant. |
| Token forgery | Asymmetric signatures only — RS256, ES256, EdDSA. Before the library: a token of at most `Jws::MAX_LENGTH` bytes, three parts, a header and claims that are JSON objects of bounded depth, a header naming an algorithm and a `kid`, no `crit` and no `b64`. Then the `kid` must name a published key, the header's algorithm must be the key's own `alg`, and the library verifies with that algorithm alone: `none`, HMAC and algorithm confusion are refused. |
| Keys too weak or of the wrong kind | New keys: RSA of 3072 bits, P-256, Ed25519, made by `JWKFactory`. A key read back from the store must match the type and curve of its algorithm and hold every private member; an RSA modulus under 2048 bits is refused. |
| Request objects | Not accepted: `request` and `request_uri` are refused with `request_not_supported` and `request_uri_not_supported` before anything reads them. The one JSON Web Token a client sends is a client assertion, bounded like every token before the library sees it, and verified only with a public key the client registered. |
| Mix-up between providers | The issuer in the authorization response (RFC 9207). |
| Several keys active at once | The first key and each rotation under one shared lock; extra active or pending keys settled at every read. |
| Key compromise | Rotation with publication before use; `rotate-keys --now`; private keys sealed by the KeyManagement component when configured. |
| A client reading another client's tokens | Introspection and revocation answer only for the asking client's tokens. |
| A disabled account keeping its access | Introspection answers `active: false` for the tokens of a disabled account, as userinfo refuses them. |
| A disabled client keeping its access | A disabled client cannot authenticate to the token, revocation and introspection endpoints, and userinfo refuses the tokens issued to it. |
| A client left signed in after the session ended | With `oidc.backchannel_logout`, every client the session issued tokens to receives a signed logout token, tried again by Messenger until it answers; the message carries identifiers only, and the token is signed when sent. |
| A logout address used to reach the provider's network | The address is registered by the console, `https` and read by `phil/url`; no redirect is followed, and each attempt is bounded by `oidc.backchannel_logout.timeout`. |
| A client asking for an account chooser | The provider shows none: `prompt=select_account` is refused with `invalid_request` and is not in the discovery document, so no client takes the current account for a chosen one. |

## Token families

A family is every token issued from one authorization code, each rotation of its refresh token included. It is the unit
the provider revokes when it sees the sign of a theft.

| Threat | Defence |
|---|---|
| A stolen refresh token used after the client rotated it | The rotated token presented again revokes the family — the thief's tokens and the client's —, and `identity.oidc.token_reuse` is journaled with the family. |
| A stolen refresh token used before the client | The thief's refresh rotates the token; the client's next refresh presents the rotated token and revokes the family, the thief's new tokens with it. The thief keeps an access token until it expires, `oidc.access_token_lifetime`. |
| Two refreshes racing to rotate one token | One conditional `UPDATE` names the single winner; the other is a reuse. The tests play two requests interleaved and two database connections. |
| A family surviving the end of its session | Closing the session revokes the tokens issued in it; a refresh token of a closed, purged or too old session is refused and revokes its family. |
| A revoked family replayed | A revoked refresh token is never active again: a replay is refused, without a new family. |

## Account linking

An external identity never merges into an account on an address its provider did not verify; on a verified one only
when the option is on and the account verified it too. Unlinking keeps a way to sign in.

## Privacy

The bundle keeps no address of a connection, a coarse device name only, no token of a federated provider. With
`oidc.subject.type: pairwise`, clients of different sectors receive different subjects for one account and cannot match
their people by it; with `public`, the internal identifier never leaves the provider. Its events and
audit rows carry identifiers, never an address, a name, a code or a secret.

## What the application keeps

The firewall's rate limits, the transport security (HTTPS, HSTS, secure cookies), the security headers, the secrets
vault holding `secret`, the database's access controls, the backup and erasure policy, and the review of its own
templates when it replaces the bundle's.

## Open risks

| Risk | Why it stays | What reduces it |
|---|---|---|
| A confidential client registered with `--pkce-optional` | The OpenID Connect Basic profile lets a confidential client go without PKCE, and the conformance suite plays such a client. | Off by default; such a client sends a `nonce`, which the ID token carries back; a public client can never go without. |
| Secrets kept in clear on purpose | With `secrets.allow_plaintext_secrets: true`, the private JSON Web Keys and the TOTP secrets are kept in clear in the database. | Off by default: without it, the modules `totp` and `oidc` refuse to start with no `secrets.kms_key`; restrict access to `phil_identity_signing_key` and `phil_identity_totp`. |
| Secrets written before the key | Setting `secrets.kms_key` encrypts what is written from then on; the rows written in clear before stay readable in clear, and the cipher still opens them. A TOTP secret is sealed again only when the person enrols again, a signing key only when it is replaced. | Rotate the signing keys (`rotate-keys --now`, then wait for `oidc.retired_key_retention`) and ask the people with an authenticator application to enrol again; until then, those rows are as exposed as before the key. |
| The cost of RS256 | The library rebuilds the PEM of an RSA key at each signature: a few milliseconds per RS256 signature, against well under a millisecond for ES256 or EdDSA (`tests/Benchmark/JwsBench.php`). A flood of token requests costs more processor time with RS256. | The firewall's rate limits on the token endpoint; `oidc.algorithm: ES256` or `EdDSA` where the clients support them. |
| `web-token/jwt-library` itself | A flaw in the library is a flaw in the provider. | One maintained library; its version follows the security advisories (`composer deps:audit`); untrusted input bounded before it. |
| A code presented by two requests at once | The request that loses the code revokes its family as a reuse; when it does so before the winner has stored its tokens, those tokens stay valid. | Rare: two exchanges of one code within milliseconds; bounded by `oidc.access_token_lifetime` and, for a refresh token, by its next rotation or revocation; the reuse is journaled as `identity.oidc.token_reuse`. |
| Refresh tokens outlive the session | With `oidc.refresh_token_bound_to_session: false`, or without the module `sessions`, a refresh token lasts `oidc.refresh_token_lifetime` whatever happens to its session; a token issued without a session always does. | On by default: the end of the session refuses its refresh tokens and revokes their family; `oidc.session_max_age` bounds them after the session opened. |
| The winner of two refreshes at once loses its tokens | The second of two refreshes of one token is refused as a reuse and revokes the family, the winner's new tokens included: a client that refreshes twice at once signs its person out. | The safe side of the race: a stolen token cannot win silently. A client refreshes once at a time. |
| Client assertions on several servers | A store of used assertions local to each server would accept one assertion once per server. | `phil_identity_assertion` lives in the provider's database, shared as the tokens are. |
| A new address set before it is proven | A change of address from the account page takes effect at once, unverified, and the former address is not told. | The change needs a recent proof of the strongest method (`PHIL_STEP_UP`); the new address stays unverified until its link is used. A notice to the former address and a change held until the new one is proven are planned. |
| Telling a known address by the time of registration | A registration for a known address answers without hashing a password, so it answers sooner. | The registration limits per client and per address bound the guesses; the answer and its status are the same. |
| Certification | The suite runs locally; certification by the OpenID Foundation needs a provider at a public address. | Done before the provider is used in production; see [OpenID conformance](openid-conformance.md). |

## The review before production

The reviewer follows the [reviewer guide](security-review.md): the code of `Application\Jose`, `Domain\WebAuthn`,
`Domain\Oidc`, `Application\Oidc`, `Infrastructure\Security` and `Infrastructure\Http\Oidc` against this page, the
tests, the fuzz targets and the [OpenID conformance suite](openid-conformance.md), and files what they find privately as
the [security policy](../SECURITY.md) explains.
