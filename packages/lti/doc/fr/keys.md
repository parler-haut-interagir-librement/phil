<!-- phil-dev source-sha256=76017588d80e0919b9de54eb065f82b9e0b214e11b77f38d1750ee4a766554a0 -->
# Clés

Chaque côté signe avec sa propre clé RSA et publie la moitié publique dans un jeu de clés ; l'autre côté vérifie avec
elle.

## Les clés de ce côté

`KeyStore` donne la clé de signature et le jeu de clés à publier. `InMemoryKeyStore` garde une clé courante et les clés
publiques des rotations précédentes :

```php
use Phil\Lti\Key\InMemoryKeyStore;
use Phil\Lti\Key\RsaPrivateKey;
use Phil\Lti\Key\RsaPublicKey;

$keys = new InMemoryKeyStore(
    RsaPrivateKey::fromPem($pemFromTheEnvironment, keyId: '2026-11'),
    [RsaPublicKey::fromPem($previousPublicPem, '2026-08')],
);

$keys->signingKey();               // signs every token of this side
$keys->publicKeys()->toJson();     // the key set to publish
```

Une clé fait de 2048 à 8192 bits. Sans identifiant de clé, elle est nommée par son empreinte de la RFC 7638. Une clé
est une JSON Web Key de `web-token/jwt-library` : `RsaPrivateKey::jwk()` et `RsaPublicKey::$jwk` la donnent au code
qui emploie la bibliothèque directement. `RsaPrivateKey::fromJwk()` lit une JSON Web Key privée, `fromPem()` une clé en
PEM, chiffrée ou non ; `RsaPrivateKey::generate()` en fabrique une nouvelle ; `toJwk()` et `toJson()` l'écrivent pour
un magasin qui la scelle, et `fromJson()` relit le JSON, de 64 Kio et quatre niveaux de profondeur au plus.

## Rotation

Pour faire une rotation : fabriquez une nouvelle clé et signez avec elle ; continuez de publier la clé publique
précédente pendant un chevauchement plus long que la vie d'un jeton — cinq minutes pour un lancement, davantage pour un
jeu de clés de l'autre côté gardé en cache, une heure par défaut — ; puis retirez-la. `phil/lti-bundle` le fait dans une
table, les clés privées scellées par un service de gestion de clés.

## Les clés de l'autre côté

`KeySetSource` lit le jeu de clés de l'autre côté par son adresse. `SafeHttpKeySetSource` le lit par `phil/safe-http` :
HTTPS seulement, réseaux privés refusés, redirections vérifiées, 256 Kio et dix secondes au plus. `CachedKeySetSource`
le garde une heure par défaut. Lorsqu'un jeton nomme une clé que le jeu en cache ne contient pas — l'autre côté a fait
une rotation —, `JwtVerifier` redemande le jeu une seule fois ; le cache ne le lit qu'une fois par minute au plus par
adresse, de sorte que des jetons qui nomment des clés inconnues ne peuvent pas obliger le côté à lire le jeu à chaque
requête.

Un jeu de clés garde les clés RSA pour `RS256` et ignore les autres ; il contient 32 clés au plus.
