<!-- phil-dev source-sha256=7db74bf65c0ea1aef8dd02798f17c674db94fe5e55d39ca8a1c65025b06343a2 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent avec les extensions `dom`, `json`, `libxml`, `mbstring`, `openssl`, `sodium` et `zlib` ;
`phil/url`, `phil/safe-http` et `symfony/service-contracts`, qui résolvent un émetteur `did:web` quand l'application le
demande.

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
