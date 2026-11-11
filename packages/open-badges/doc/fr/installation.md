<!-- phil-dev source-sha256=9c0ab1846d920ddc77ee965ec777f2eb68b615d2dc09f7c6e6eaaf4e88e649d8 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent avec les extensions `dom`, `json`, `libxml`, `mbstring`, `openssl`, `sodium` et `zlib` ; rien
d'autre.

```sh
composer require phil/open-badges
```

## La clé de l'émetteur

L'émetteur signe avec une seule clé privée, gardée comme un secret de l'application — jamais dans le code ni dans la
base de données :

| Clé | Signataire | Sécurise |
|---|---|---|
| Ed25519, un germe de 32 octets | `Ed25519Signer::fromSeed($seed)` | Les JSON Web Tokens (`EdDSA`) et les preuves Data Integrity (`eddsa-rdfc-2022`) |
| RSA de 2048 bits ou plus, en PEM | `RsaSigner::fromPem($pem, $passphrase)` | Les JSON Web Tokens (`RS256`), l'algorithme que tous les vérificateurs acceptent |

Un germe se tire une seule fois, par exemple avec `php -r 'echo base64_encode(random_bytes(32));'`, et se stocke
comme un secret.

La clé publique est publiée à l'identifiant de l'émetteur, pour que tout le monde puisse vérifier : voyez
[le document de profil](securing.md#le-document-de-profil).
