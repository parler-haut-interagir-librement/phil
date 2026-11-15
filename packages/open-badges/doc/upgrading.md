# Upgrading

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
