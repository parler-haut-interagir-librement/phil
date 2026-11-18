# Other issuers

A credential issued elsewhere is signed with its issuer's key, which the application does not configure. With
`remote_issuers` enabled, the verifier — and the verification page — read that key where the issuer publishes it: in
its profile, at its HTTPS identifier, as Open Badges 3.0 describes; or in its DID document, when its identifier is a
`did:web`. Ed25519 keys and RSA keys are read, so a token another issuer signed with `EdDSA` or `RS256`, and a
document with an `eddsa-rdfc-2022` proof, verify alike.

```yaml
# config/packages/phil_open_badges.yaml
phil_open_badges:
    remote_issuers:
        enabled: true
        trusted: []                 # any issuer on a public host; or the only identifiers read, HTTPS or did:web
        cache: cache.app            # the pool the keys read are kept in
        ttl: 3600                   # seconds the keys of a profile are kept, 60 to 86400
        failure_ttl: 60             # seconds a profile that could not be read is not asked again, 1 to 3600
        timeout: 3.0                # seconds the issuer may keep silent, 0.1 to 60
        max_duration: 5.0           # seconds the whole read may take, redirects included, 0.1 to 120
        max_bytes: 65536            # the largest profile read, 1024 to 1048576
        user_agent: 'PhilOpenBadges/0.1'
```

## How a key is found

The keys the application knows come first: its issuer's own, those of `trusted_keys`, and Ed25519 `did:key`s. A
verification method none of them holds is looked up in the document of its identifier without the fragment:

| Method | Issuer | Document read |
|---|---|---|
| `https://partner.example/issuer#key-1` | `https://partner.example/issuer` | `https://partner.example/issuer` |
| `did:web:partner.example#key-1` | `did:web:partner.example` | `https://partner.example/.well-known/did.json` |
| `did:web:partner.example:badges:2026#key-1` | `did:web:partner.example:badges:2026` | `https://partner.example/badges/2026/did.json` |
| `did:web:partner.example%3A8443#key-1` | `did:web:partner.example%3A8443` | `https://partner.example:8443/.well-known/did.json` |

`phil/open-badges` has already checked that the method is under the identifier of the credential's issuer, so a
document only ever vouches for its own credentials.

| Step | Rule |
|---|---|
| The address | An HTTPS address read by `phil/url`: no user name nor password, no IP address, no `localhost` unless `allow_localhost` is true. A `did:web` gives its address as `DidWeb` of `phil/open-badges` builds it from the did:web method specification, refused rather than repaired: a host name, never an IP address nor `localhost` — whatever `allow_localhost` says —, an optional port written `%3A`, path segments of unreserved characters or percent-encoded bytes, none of them `.` nor `..` nor holding a slash, no query nor fragment. Any other `did:`, a `urn` or plain HTTP is not read, and stays unknown. |
| The trusted issuers | When `trusted` lists identifiers, any other issuer is refused before anything is read. |
| The read | `phil/safe-http`: a host that is or resolves to a private, loopback, link-local or reserved address is never reached, redirects are checked one by one, three at most, and the last address read is still HTTPS, and the silence, the duration and the bytes are bounded. `Accept: application/ld+json, application/json`; another media type is not read. |
| The document | A JSON object, 32 levels deep at most, whose `id` is the issuer's identifier: the address read for a profile, the `did:web` itself for a DID document. |
| The keys | The first 20 entries of `verificationMethod`; each whose `id` is a fragment of the identifier — written whole, or as the fragment alone, `#key-1` —, whose `controller`, when given, is the identifier, and, when the document lists an `assertionMethod`, which it lists; an Ed25519 key as a `Multikey` with its `publicKeyMultibase` or as a `JsonWebKey` with its `publicKeyJwk` (`OKP`, `Ed25519`); an RSA key as a `JsonWebKey` with its `publicKeyJwk` (`RSA`, its `n` and `e`, no private member, an `alg` when given `RS256`), read by `RsaKey::fromJwk()` of `phil/open-badges`: its modulus of 2048 to 8192 bits, its exponent odd, at least 3 and of at most 64 bits. Any other entry is left aside. |

The keys found are kept in `cache` for `ttl` seconds, under `phil_open_badges.issuer.<sha256 of the identifier>`, as
JSON Web Keys reduced to what verifies: a verifier reads a document once in a while, however many credentials it
checks. A document that does not hold is kept refused for `failure_ttl` seconds, so that an issuer that does not
answer is not asked again at each check.

## The refusals

A credential whose key cannot be found does not verify, with a reason the page shows. The reasons say "profile" for
a DID document too; the first four name the issuer by its identifier, the `did:web` itself, and the fifth names the
address read:

| Reason | When |
|---|---|
| `The issuer "…" is not one the verifier trusts.` | `trusted` does not list it. |
| `The profile of the issuer "…" could not be read: …` | The address is refused, the host does not answer in time, the redirects are too many, or the answer is not a `2xx`. |
| `The profile of the issuer "…" was redirected away from HTTPS.` | A redirect led to a plain HTTP address: the profile is not read, whatever it holds. |
| `The profile of the issuer "…" is not JSON within the bytes the verifier reads.` | Another media type, a body cut at `max_bytes`, JSON that does not parse or is not an object. |
| `The profile read at "…" is not the profile of that issuer: its "id" differs.` | The document read at that address names another identifier, or none. |
| `The profile of the issuer "…" publishes no key "…".` | The method is not among the keys kept. |

## Load and abuse

Each check of the verification page may read one document. The limiter of `verification.rate_limit` already counts
every check, the reads of other issuers' documents with them ([Verification page](verification-page.md#limiting-the-checks)); a
site that opens the page to anyone names one. A service of the application that verifies credentials through the
`CredentialVerifier` service reads documents too, and bounds its own callers.

The client keeps nothing of a read between requests: it is reset with the kernel, as a worker does between messages.
An RSA modulus is bounded at 8192 bits, so that a document cannot make each check cost a slow verification; a larger
key is left aside, as one under 2048 bits is.
