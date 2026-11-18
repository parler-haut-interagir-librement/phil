# Upgrading

## From 0.2 to 0.3

The API is the same; what the library accepts is stricter. A credential the library issued with 0.2 verifies with 0.3.

- `validFrom` and `validUntil` are read as strict moments of RFC 3339, by issuers and verifier alike: an upper-case
  `T`, seconds, `Z` or an offset, each part within its range. A credential built by hand with `2026-02-30T00:00:00Z`,
  a leap second or a lower-case `t` is refused where it was carried over to another date.
- `JwtSigner::sign()` refuses a credential that carries `iss`, `jti`, `sub`, `nbf` or `exp`, and identifiers that are
  not strings: remove the claims, which it writes from the credential's fields.
- `CredentialVerifier` refuses a token whose registered claims contradict its credential, and checks a token's
  `validFrom` and `validUntil` as it checks a document's. A token of another issuer whose `exp` differs from its
  `validUntil` no longer verifies.
- A context that redefines a protected term — `id`, `type`, `name` of the Verifiable Credentials context, and the terms
  of a credential through a second type — is refused with `InvalidCredential`.
- `PngBaker` refuses an image with bytes after `IEND`, where it dropped them, and an image whose chunks are out of
  order: an empty `IEND` after the image data, a single `IHDR` of 13 bytes first.

## From 0.1 to 0.2

Tokens and Data Integrity proofs are signed and verified by `web-token/jwt-library`. The tokens, the proofs and the
keys an issuer publishes are the same: a credential secured with 0.1 verifies with 0.2, and the other way round.

### `Contract\Signer`

`sign(string $data): string` is replaced by two methods, which `JwtSigner` hands to the library:

| Method | Returns |
|---|---|
| `signatureAlgorithm(): SignatureAlgorithm` | The `Jose\Component\Signature\Algorithm\SignatureAlgorithm` named by `algorithm()`. |
| `jwk(): JWK` | The `Jose\Component\Core\JWK` that algorithm signs with. |

`Ed25519Signer` and `RsaSigner` implement them. A signer of the application's own over a key management service returns
a `SignatureAlgorithm` that signs through the service, and the public JSON Web Key as `jwk()`.

### `Contract\VerificationKey`

`verify(string $data, string $signature): bool` is replaced by `jwk(): JWK`, the public key the verifier hands to the
library with the algorithm `algorithm()` names. A verification key of the application's own returns its public JSON
Web Key; its `algorithm()` is `EdDSA` or `RS256`, any other verifying nothing.

### Keys

- `RsaKey::details()` and `RsaKey::text()` are removed; `RsaKey::toJwk()` gives the public JSON Web Key.
- `RsaKey::fromPem()` refuses a modulus under 2048 or over 8192 bits, as `RsaKey::fromJwk()` does.
- `RsaSigner::fromPem()` refuses a modulus over 8192 bits (`RsaSigner::MAX_BITS`) and a key that is not an RSA private
  key.
