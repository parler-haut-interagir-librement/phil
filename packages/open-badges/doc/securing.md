# Securing

A credential is secured in one of the two ways Open Badges 3.0 recognizes. Both are verified offline with the issuer's
public key.

| | JSON Web Token | Data Integrity |
|---|---|---|
| Form | A compact token: `header.claims.signature` | The JSON document with a `proof` |
| Keys | Ed25519 (`EdDSA`) or RSA (`RS256`) | Ed25519 (`eddsa-rdfc-2022`) |
| Signed bytes | The token's header and claims | The SHA-256 of the canonical forms of the proof's options and of the document |
| Preferred by | Most verifiers and backpacks | Wallets of Verifiable Credentials, learner records |

## JSON Web Tokens

```php
use Phil\OpenBadges\Jwt\JwtSigner;

$token = new JwtSigner($signer, 'https://example.org/issuer#key-1')->sign($credential);
```

The header names the algorithm, the type `vc+jwt`, the key's identifier (`kid`) when one is given, and the public key
as a JSON Web Key. The claims are the credential's, with `iss`, `jti`, `sub`, `nbf` and `exp` mirroring its issuer,
identifier, subject's identifier and period of validity. `CredentialIssuer::issue()` does this for an
`OpenBadgeCredential`; `JwtSigner` secures any credential the library builds — an endorsement, a learner record, a
status list.

## Data Integrity

```php
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;

$secured = new EddsaRdfc2022()->sign($credential, $ed25519Signer, 'https://example.org/issuer#key-1', $now);
```

The proof is a `DataIntegrityProof` of the cryptosuite `eddsa-rdfc-2022`, for the purpose `assertionMethod`, created
at the moment given and naming its verification method. Its value is the Ed25519 signature, in base 58, of the
SHA-256 of the canonical form of the proof's options followed by the SHA-256 of the canonical form of the document.

The canonical form is the RDF Dataset Canonicalization RDFC-1.0 of the document read as JSON-LD. The library reads
JSON-LD in safe mode: a property that no context defines, a type or an identifier that is not an absolute IRI, is
refused rather than left out of what is signed. `canonicalize()` returns the canonical N-Quads, to see what a proof
covers.

A document is signed once: one with a proof already is refused.

## The profile document

The issuer publishes its profile at its identifier, with its public keys:

```php
$profile = $issuer->profileDocument(['https://example.org/issuer#key-1' => $ed25519Signer->verificationKey()]);
```

It is the issuer's `Profile` with its contexts and, for each key, a `Multikey` verification method — its
`publicKeyMultibase` — that the profile asserts with. A verification method is a fragment of the issuer's identifier.
For JSON Web Tokens, the application also publishes its keys as a JSON Web Key Set: `$signer->publicJwk()`, with the
key's identifier as `kid`. `phil/open-badges-bundle` serves both.
