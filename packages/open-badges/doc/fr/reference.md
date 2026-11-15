<!-- phil-dev source-sha256=8b5338bb018330628e5cc179c909ee67a45a743952819e6bb01276df83a96368 -->
# Référence

## `Phil\OpenBadges`

| Type | Rôle |
|---|---|
| `Issuer` | Le `Profile` de l'émetteur, intégré à ses titres ; `profileDocument()` avec ses méthodes de vérification Multikey. |
| `Achievement` | Ce qui est reconnu, avec ses critères, son image et ses appuis. |
| `Recipient` | Un DID, ou une empreinte salée d'une adresse courriel ou d'une URL ; `identifies()` vérifie qui présente un titre. |
| `CredentialIssuer` | Construit un `OpenBadgeCredential` ; `issue()` le sécurise sous forme de JSON Web Token. |
| `EndorsementIssuer` | Construit l'`EndorsementCredential` que signe un tiers. |
| `Endorsements` | Les champs `endorsement` et `endorsementJwt`. |
| `Context` | Les URL des contextes JSON-LD. |
| `Credentials` | Interne : identifiants et périodes de validité. |

## `Phil\OpenBadges\Key` et `Phil\OpenBadges\Contract`

| Type | Rôle |
|---|---|
| `Contract\Signer`, `Contract\VerificationKey` | Une clé privée qui signe, une clé publique qui vérifie. |
| `Contract\VerificationMethods` | Trouve la clé que nomme une méthode de vérification. |
| `Contract\ContextLoader` | Donne le contexte JSON-LD d'une URL. |
| `Contract\DocumentFetcher` | Lit le document DID d'un `did:web` à son adresse. |
| `Key\Ed25519Signer`, `Key\Ed25519Key` | `EdDSA` par `web-token/jwt-library` ; JSON Web Keys et Multikeys. |
| `Key\RsaSigner`, `Key\RsaKey` | `RS256` par `web-token/jwt-library`, un module de 2048 à 8192 bits ; lue depuis un PEM ou depuis une JSON Web Key. |

## Sécuriser et vérifier

| Type | Rôle |
|---|---|
| `Jwt\JwtSigner`, `Jwt\JwtVerifier` | VC-JWT par `web-token/jwt-library`, l'algorithme étant pris de la clé. |
| `DataIntegrity\EddsaRdfc2022` | La suite cryptographique `eddsa-rdfc-2022` : `sign()`, `verify()`, `canonicalize()`. |
| `DataIntegrity\DidKeyMethods` | Les clés de l'application et chaque `did:key` Ed25519. |
| `Did\DidWeb` | Un identifiant `did:web` et l'adresse HTTPS de son document DID. |
| `Did\DidWebMethods` | Les clés des émetteurs `did:web` lues dans leurs documents DID, les autres méthodes transmises. |
| `Adapter\SafeHttpFetcher` | Le `DocumentFetcher` sur `phil/safe-http`. |
| `JsonLd\BundledContexts` | Les contextes inclus et ceux que l'application ajoute. |
| `JsonLd\ToRdf`, `JsonLd\Conversion`, `JsonLd\ContextProcessor`, `JsonLd\ActiveContext`, `JsonLd\TermDefinition`, `JsonLd\TermDefining` | Interne : JSON-LD vers RDF en mode sûr. |
| `Rdf\Canonicalizer`, `Rdf\IdentifierIssuer`, `Rdf\NQuads` | Interne : RDFC-1.0 et N-Quads canoniques. |
| `Verification\CredentialVerifier`, `Verification\VerifiedCredential` | Un jeton ou un document vérifié, avec sa période et son entrée d'état. |

## États, relevés, images

| Type | Rôle |
|---|---|
| `Status\StatusListEntry` | Le `credentialStatus` d'un titre. |
| `Status\StatusList` | Les bits d'une Bitstring Status List, encodés et décodés. |
| `Status\StatusListIssuer` | Le `BitstringStatusListCredential` ; `applies()` y lit un état. |
| `Clr\ClrIssuer` | Un Comprehensive Learner Record 2.0. |
| `Baking\PngBaker`, `Baking\SvgBaker` | Un titre intégré dans une image et lu dans celle-ci. |
| `Baking\Baked` | Interne : ce qu'une image peut contenir. |

## `Phil\OpenBadges\Encoding` et `Phil\OpenBadges\Exception`

| Type | Rôle |
|---|---|
| `Encoding\Base64Url`, `Encoding\Base58Btc`, `Encoding\Multibase` | Les encodages des jetons, des preuves, des clés et des listes. |
| `Exception\OpenBadgesException` | Implémentée par chaque exception de la bibliothèque. |
| `Exception\InvalidBadge`, `Exception\InvalidCredential`, `Exception\InvalidImage` | Voyez [Exceptions](exceptions.md). |
