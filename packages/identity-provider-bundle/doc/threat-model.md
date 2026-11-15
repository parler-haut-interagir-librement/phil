# Threat model

What the bundle defends against, how, and what it leaves to the application. A security review by someone who did not
write the code comes before the bundle is used in production, against this page and the
[reviewer guide](security-review.md).

## Assets

| Asset | Where it lives | What protects it |
|---|---|---|
| Password hashes | `phil_identity_account` | Argon2id; never logged, never in an event. |
| Passkey public keys and counters | `phil_identity_passkey` | Public by nature; the counter must move forward. |
| TOTP secrets, recovery code hashes | `phil_identity_totp`, `phil_identity_recovery_codes` | Sealed by the `SecretCipher` (KeyManagement component) when a key is configured; codes keyed with `secret`. |
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
| Guessing passwords online | The firewall's `login_throttling` per address and network; the progressive lock per account, checked before the password; passkeys still sign a locked account in. |
| Weak or breached passwords | `phil/passwords`: strength estimator with the account's own data as first guesses; Pwned Passwords by k-anonymity when turned on. |
| Stolen password database | Argon2id; no password in a log, a message or an event. |
| Phishing | Passkeys: the browser signs only for the relying party; origins checked. |
| Replayed TOTP code | The counter accepted is kept; a code verifies once. |
| Cloned authenticator | The signature counter must move forward when the authenticator keeps one. |
| Malformed authenticator answers | Bounded CBOR reader: depth, items and lengths checked before allocation; fuzzed. |
| Account enumeration | Registration, password reset and sign-in links answer the same for known and unknown addresses. |
| Reused links | Links signed over the properties they change — the address and its verification, the password hash and its date —, so each works once with nothing stored. |

## Session attacks

| Threat | Defence |
|---|---|
| Session fixation | Symfony Security migrates the session at sign-in. |
| A stolen device | Sessions closed from another device take effect at the next request; tokens issued in them are revoked. |
| A session opened long ago used for a sensitive action | `PHIL_STEP_UP` asks for a recent proof of the strongest method. |
| A second factor skipped | The second step is enforced on every request until a strong proof is on the token. |
| Cross-site request forgery | Every form and script request carries a CSRF token; logout from a link asks to confirm. |

## Provider attacks

| Threat | Defence |
|---|---|
| Open redirect, code theft through a redirect address | Exact match with a registered address, read by `phil/url`; errors about the client shown, not redirected. |
| Code interception or injection | PKCE S256 required of every public client and, by default, of every confidential one; a code issued without a challenge refuses a verifier at the token endpoint (RFC 9700, section 2.1.1); codes hashed, short-lived, used once, reuse revoking the family. |
| Refresh token theft | Rotation with reuse detection revoking the family. |
| Token forgery | Asymmetric signatures only — RS256, ES256, EdDSA. Before the library: a token of at most `Jws::MAX_LENGTH` bytes, three parts, a header and claims that are JSON objects of bounded depth, a header naming an algorithm and a `kid`, no `crit` and no `b64`. Then the `kid` must name a published key, the header's algorithm must be the key's own `alg`, and the library verifies with that algorithm alone: `none`, HMAC and algorithm confusion are refused. |
| Keys too weak or of the wrong kind | New keys: RSA of 3072 bits, P-256, Ed25519, made by `JWKFactory`. A key read back from the store must match the type and curve of its algorithm and hold every private member; an RSA modulus under 2048 bits is refused. |
| Request objects and client assertions | Not accepted: `request` and `request_uri` are refused with `request_not_supported` and `request_uri_not_supported` before anything reads them; clients authenticate by secret or, public, by PKCE. No JSON Web Token from a client reaches the library. |
| Mix-up between providers | The issuer in the authorization response (RFC 9207). |
| Key compromise | Rotation with publication before use; `rotate-keys --now`; private keys sealed by the KeyManagement component when configured. |
| A client reading another client's tokens | Introspection and revocation answer only for the asking client's tokens. |

## Account linking

An external identity never merges into an account on an address its provider did not verify; on a verified one only
when the option is on and the account verified it too. Unlinking keeps a way to sign in.

## Privacy

The bundle keeps no address of a connection, a coarse device name only, no token of a federated provider. Its events and
audit rows carry identifiers, never an address, a name, a code or a secret.

## What the application keeps

The firewall's rate limits, the transport security (HTTPS, HSTS, secure cookies), the security headers, the secrets
vault holding `secret`, the database's access controls, the backup and erasure policy, and the review of its own
templates when it replaces the bundle's.

## Open risks

| Risk | Why it stays | What reduces it |
|---|---|---|
| A confidential client registered with `--pkce-optional` | The OpenID Connect Basic profile lets a confidential client go without PKCE, and the conformance suite plays such a client. | Off by default; such a client sends a `nonce`, which the ID token carries back; a public client can never go without. |
| A key store without the KeyManagement component | The private JSON Web Keys are then kept in clear in the database. | Configure `secrets.kms_key`; restrict access to `phil_identity_signing_key`. |
| The cost of RS256 | The library rebuilds the PEM of an RSA key at each signature: a few milliseconds per RS256 signature, against well under a millisecond for ES256 or EdDSA (`tests/Benchmark/JwsBench.php`). A flood of token requests costs more processor time with RS256. | The firewall's rate limits on the token endpoint; `oidc.algorithm: ES256` or `EdDSA` where the clients support them. |
| `web-token/jwt-library` itself | A flaw in the library is a flaw in the provider. | One maintained library; its version follows the security advisories (`composer deps:audit`); untrusted input bounded before it. |
| Certification | The suite runs locally; certification by the OpenID Foundation needs a provider at a public address. | Done before the provider is used in production; see [OpenID conformance](openid-conformance.md). |

## The review before production

The reviewer follows the [reviewer guide](security-review.md): the code of `Application\Jose`, `Domain\WebAuthn`,
`Domain\Oidc`, `Application\Oidc`, `Infrastructure\Security` and `Infrastructure\Http\Oidc` against this page, the
tests, the fuzz targets and the [OpenID conformance suite](openid-conformance.md), and files what they find privately as
the [security policy](../SECURITY.md) explains.
