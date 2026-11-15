<!-- phil-dev source-sha256=1145ae1e9b66ae110b98f6085e5d9843683ac59a9aa4f4dea6c233ed84fe3a7a -->
# Mise à niveau

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
