# Reference

## `Phil\OpenBadges`

| Type | Role |
|---|---|
| `Issuer` | The issuer's `Profile`, embedded in its credentials; `profileDocument()` with its Multikey verification methods. |
| `Achievement` | What is recognized, with its criteria, image and endorsements. |
| `Recipient` | A DID, or a salted hash of an e-mail address or URL; `identifies()` checks who presents a credential. |
| `CredentialIssuer` | Builds an `OpenBadgeCredential`; `issue()` secures it as a JSON Web Token. |
| `EndorsementIssuer` | Builds the `EndorsementCredential` a third party signs. |
| `Endorsements` | The `endorsement` and `endorsementJwt` fields. |
| `Context` | The URLs of the JSON-LD contexts. |
| `Credentials` | Internal: identifiers and periods of validity. |

## `Phil\OpenBadges\Key` and `Phil\OpenBadges\Contract`

| Type | Role |
|---|---|
| `Contract\Signer`, `Contract\VerificationKey` | A private key that signs, a public key that verifies. |
| `Contract\VerificationMethods` | Finds the key a verification method names. |
| `Contract\ContextLoader` | Gives the JSON-LD context of a URL. |
| `Key\Ed25519Signer`, `Key\Ed25519Key` | `EdDSA` through libsodium; JSON Web Keys and Multikeys. |
| `Key\RsaSigner`, `Key\RsaKey` | `RS256` through OpenSSL, 2048 bits or more. |

## Securing and verifying

| Type | Role |
|---|---|
| `Jwt\JwtSigner`, `Jwt\JwtVerifier` | VC-JWT, the algorithm taken from the key. |
| `DataIntegrity\EddsaRdfc2022` | The `eddsa-rdfc-2022` cryptosuite: `sign()`, `verify()`, `canonicalize()`. |
| `DataIntegrity\DidKeyMethods` | The application's keys and every Ed25519 `did:key`. |
| `JsonLd\BundledContexts` | The bundled contexts and those the application adds. |
| `JsonLd\ToRdf`, `JsonLd\Conversion`, `JsonLd\ContextProcessor`, `JsonLd\ActiveContext`, `JsonLd\TermDefinition`, `JsonLd\TermDefining` | Internal: JSON-LD to RDF in safe mode. |
| `Rdf\Canonicalizer`, `Rdf\IdentifierIssuer`, `Rdf\NQuads` | Internal: RDFC-1.0 and canonical N-Quads. |
| `Verification\CredentialVerifier`, `Verification\VerifiedCredential` | A token or a document verified, with its period and status entry. |

## Status, records, images

| Type | Role |
|---|---|
| `Status\StatusListEntry` | A credential's `credentialStatus`. |
| `Status\StatusList` | The bits of a Bitstring Status List, encoded and decoded. |
| `Status\StatusListIssuer` | The `BitstringStatusListCredential`; `applies()` reads a status from it. |
| `Clr\ClrIssuer` | A Comprehensive Learner Record 2.0. |
| `Baking\PngBaker`, `Baking\SvgBaker` | A credential baked into and read from an image. |
| `Baking\Baked` | Internal: what an image may hold. |

## `Phil\OpenBadges\Encoding` and `Phil\OpenBadges\Exception`

| Type | Role |
|---|---|
| `Encoding\Base64Url`, `Encoding\Base58Btc`, `Encoding\Multibase` | The encodings of tokens, proofs, keys and lists. |
| `Exception\OpenBadgesException` | Implemented by every exception of the library. |
| `Exception\InvalidBadge`, `Exception\InvalidCredential`, `Exception\InvalidImage` | See [Exceptions](exceptions.md). |
