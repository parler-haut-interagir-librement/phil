<!-- phil-dev source-sha256=2e2f537b419f452e4c48a868aecb9ba5d4d9102ab2da1f865bc9a96070538e98 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent avec les extensions `dom`, `json`, `libxml`, `mbstring`, `openssl`, `sodium` et `zlib` ;
`web-token/jwt-library`, qui signe et vérifie les jetons et les preuves — `RS256` par `openssl`, `EdDSA` par
`sodium` ; `phil/url`, `phil/safe-http` et `symfony/service-contracts`, qui résolvent un émetteur `did:web` quand
l'application le demande.

```sh
composer require phil/open-badges
```

## La clé de l'émetteur

L'émetteur signe avec une seule clé privée, gardée comme un secret de l'application — jamais dans le code ni dans la
base de données :

| Clé | Signataire | Sécurise |
|---|---|---|
| Ed25519, un germe de 32 octets | `Ed25519Signer::fromSeed($seed)` | Les JSON Web Tokens (`EdDSA`) et les preuves Data Integrity (`eddsa-rdfc-2022`) |
| RSA de 2048 à 8192 bits, en PEM | `RsaSigner::fromPem($pem, $passphrase)` | Les JSON Web Tokens (`RS256`), l'algorithme que tous les vérificateurs acceptent |

Un germe se tire une seule fois, par exemple avec `php -r 'echo base64_encode(random_bytes(32));'`, et se stocke
comme un secret.

La clé publique est publiée à l'identifiant de l'émetteur, pour que tout le monde puisse vérifier : voyez
[le document de profil](securing.md#le-document-de-profil).
