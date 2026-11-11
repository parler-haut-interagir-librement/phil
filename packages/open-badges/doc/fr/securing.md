<!-- phil-dev source-sha256=c1bdfee76ea73df3b2bec4d8de915935020cb766b7c6fb5514f516c4bef03f83 -->
# Sécuriser

Un titre est sécurisé de l'une des deux façons que reconnaît Open Badges 3.0. Les deux se vérifient hors ligne avec
la clé publique de l'émetteur.

| | JSON Web Token | Data Integrity |
|---|---|---|
| Forme | Un jeton compact : `header.claims.signature` | Le document JSON avec une `proof` |
| Clés | Ed25519 (`EdDSA`) ou RSA (`RS256`) | Ed25519 (`eddsa-rdfc-2022`) |
| Octets signés | L'en-tête et les revendications du jeton | Le SHA-256 des formes canoniques des options de la preuve et du document |
| Préféré par | La plupart des vérificateurs et des sacs à dos de badges | Les portefeuilles de Verifiable Credentials, les relevés d'apprentissage |

## JSON Web Tokens

```php
use Phil\OpenBadges\Jwt\JwtSigner;

$token = new JwtSigner($signer, 'https://example.org/issuer#key-1')->sign($credential);
```

L'en-tête nomme l'algorithme, le type `vc+jwt`, l'identifiant de la clé (`kid`) quand il est donné, et la clé publique
sous forme de JSON Web Key. Les revendications sont celles du titre, avec `iss`, `jti`, `sub`, `nbf` et `exp` qui
reprennent son émetteur, son identifiant, l'identifiant de son sujet et sa période de validité.
`CredentialIssuer::issue()` le fait pour un `OpenBadgeCredential` ; `JwtSigner` sécurise tout titre que construit la
bibliothèque : un appui, un relevé d'apprentissage, une liste d'état.

## Data Integrity

```php
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;

$secured = new EddsaRdfc2022()->sign($credential, $ed25519Signer, 'https://example.org/issuer#key-1', $now);
```

La preuve est une `DataIntegrityProof` de la suite cryptographique `eddsa-rdfc-2022`, pour l'usage
`assertionMethod`, créée au moment donné et qui nomme sa méthode de vérification. Sa valeur est la signature Ed25519,
en base 58, du SHA-256 de la forme canonique des options de la preuve suivi du SHA-256 de la forme canonique du
document.

La forme canonique est la RDF Dataset Canonicalization RDFC-1.0 du document lu comme du JSON-LD. La bibliothèque lit
le JSON-LD en mode sûr : une propriété qu'aucun contexte ne définit, un type ou un identifiant qui n'est pas un IRI
absolu, est refusé plutôt qu'exclu de ce qui est signé. `canonicalize()` retourne les N-Quads canoniques, pour voir ce
que couvre une preuve.

Un document est signé une seule fois : un document qui a déjà une preuve est refusé.

## Le document de profil

L'émetteur publie son profil à son identifiant, avec ses clés publiques :

```php
$profile = $issuer->profileDocument(['https://example.org/issuer#key-1' => $ed25519Signer->verificationKey()]);
```

C'est le `Profile` de l'émetteur avec ses contextes et, pour chaque clé, une méthode de vérification `Multikey` — son
`publicKeyMultibase` — que le profil utilise pour ses assertions. Une méthode de vérification est un fragment de
l'identifiant de l'émetteur. Pour les JSON Web Tokens, l'application publie aussi ses clés sous forme de JSON Web Key
Set : `$signer->publicJwk()`, avec l'identifiant de la clé comme `kid`. `phil/open-badges-bundle` sert les deux.
