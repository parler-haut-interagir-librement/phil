# Installation

## Requirements

PHP 8.5 or later with the `dom`, `json`, `libxml`, `mbstring`, `openssl`, `sodium` and `zlib` extensions; nothing
else.

```sh
composer require phil/open-badges
```

## The issuer's key

The issuer signs with one private key, kept as a secret of the application — never in the code nor in the database:

| Key | Signer | Secures |
|---|---|---|
| Ed25519, a seed of 32 bytes | `Ed25519Signer::fromSeed($seed)` | JSON Web Tokens (`EdDSA`) and Data Integrity proofs (`eddsa-rdfc-2022`) |
| RSA of 2048 bits or more, in PEM | `RsaSigner::fromPem($pem, $passphrase)` | JSON Web Tokens (`RS256`), the algorithm every verifier accepts |

A seed is drawn once, for instance with `php -r 'echo base64_encode(random_bytes(32));'`, and stored as a secret.

The public key is published at the issuer's identifier, so that anyone can verify: see
[the profile document](securing.md#the-profile-document).
