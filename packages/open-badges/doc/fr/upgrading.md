<!-- phil-dev source-sha256=63e0c2551fe236f7b86518ae000adf548c6ac0abf36383e5bb78301079ba7764 -->
# Mise à niveau

## De 0.2 à 0.3

L'API est la même ; ce que la bibliothèque accepte est plus strict. Un titre que la bibliothèque a émis avec la 0.2 se
vérifie avec la 0.3.

- `validFrom` et `validUntil` sont lus comme des moments stricts de la RFC 3339, par les émetteurs comme par le
  vérificateur : un `T` majuscule, des secondes, `Z` ou un décalage, chaque partie dans sa plage. Un titre construit à
  la main avec `2026-02-30T00:00:00Z`, une seconde intercalaire ou un `t` minuscule est refusé, là où il était reporté à
  une autre date.
- `JwtSigner::sign()` refuse un titre qui porte `iss`, `jti`, `sub`, `nbf` ou `exp`, et des identifiants qui ne sont
  pas des chaînes : retirez les revendications, qu'il écrit à partir des champs du titre.
- `CredentialVerifier` refuse un jeton dont les revendications enregistrées contredisent son titre, et vérifie
  `validFrom` et `validUntil` d'un jeton comme ceux d'un document. Un jeton d'un autre émetteur dont l'`exp` diffère de
  son `validUntil` ne se vérifie plus.
- Un contexte qui redéfinit un terme protégé — `id`, `type`, `name` du contexte des Verifiable Credentials, et les
  termes d'un titre par un second type — est refusé avec `InvalidCredential`.
- `PngBaker` refuse une image avec des octets après `IEND`, qu'il abandonnait, et une image dont les blocs sont dans le
  désordre : un `IEND` vide après les données de l'image, un seul `IHDR` de 13 octets en premier.

## De 0.1 à 0.2

Les jetons et les preuves Data Integrity sont signés et vérifiés par `web-token/jwt-library`. Les jetons, les preuves
et les clés qu'un émetteur publie sont les mêmes : un titre sécurisé avec la 0.1 se vérifie avec la 0.2, et
inversement.

### `Contract\Signer`

`sign(string $data): string` est remplacée par deux méthodes, que `JwtSigner` remet à la bibliothèque :

| Méthode | Retourne |
|---|---|
| `signatureAlgorithm(): SignatureAlgorithm` | Le `Jose\Component\Signature\Algorithm\SignatureAlgorithm` que nomme `algorithm()`. |
| `jwk(): JWK` | Le `Jose\Component\Core\JWK` avec lequel cet algorithme signe. |

`Ed25519Signer` et `RsaSigner` les implémentent. Un signataire propre à l'application, au-dessus d'un service de
gestion de clés, retourne un `SignatureAlgorithm` qui signe par le service, et la JSON Web Key publique comme `jwk()`.

### `Contract\VerificationKey`

`verify(string $data, string $signature): bool` est remplacée par `jwk(): JWK`, la clé publique que le vérificateur
remet à la bibliothèque avec l'algorithme que nomme `algorithm()`. Une clé de vérification propre à l'application
retourne sa JSON Web Key publique ; son `algorithm()` est `EdDSA` ou `RS256`, tout autre ne vérifiant rien.

### Clés

- `RsaKey::details()` et `RsaKey::text()` sont retirées ; `RsaKey::toJwk()` donne la JSON Web Key publique.
- `RsaKey::fromPem()` refuse un module de moins de 2048 bits ou de plus de 8192 bits, comme le fait
  `RsaKey::fromJwk()`.
- `RsaSigner::fromPem()` refuse un module de plus de 8192 bits (`RsaSigner::MAX_BITS`) et une clé qui n'est pas une clé
  privée RSA.
