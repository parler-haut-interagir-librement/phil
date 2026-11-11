# phil/open-badges

**Open Badges 3.0 that anyone can verify: issued, signed, baked into the image, revoked, endorsed and gathered in a
learner record.**

phil/open-badges issues Open Badges 3.0 credentials — an `OpenBadgeCredential` in the data model of Verifiable
Credentials 2.0 — and verifies them. A credential says that an issuer recognizes an achievement of a recipient; it is
signed by the issuer's key, so that the recipient can take it anywhere — a portfolio, a professional profile, a
wallet — and anyone can check it with the issuer's public key, without asking the site that issued it.

It is for any PHP application that recognizes what people do: a training platform that awards certificates, a
community that thanks its volunteers, an event that credits its speakers. The library reads no account and stores
nothing: the application says who the recipient is, as they give it, and keeps what it issued.

It is a plain PHP library that requires only PHP and its extensions. A credential is secured in
one of the two ways Open Badges 3.0 recognizes: as a JSON Web Token (`EdDSA` or `RS256`), or with an embedded Data
Integrity proof of the `eddsa-rdfc-2022` cryptosuite, for which the library carries its own JSON-LD processor and RDF
canonicalization. Verifying never fetches anything: the JSON-LD contexts are bundled, and the keys are the ones the
application gives.

## Status

| Part | State |
|---|---|
| Issuers, achievements, recipients (DID, hashed e-mail address or URL), `OpenBadgeCredential` | Implemented and tested |
| VC-JWT with `EdDSA` (Ed25519) and `RS256` (RSA of 2048 bits or more) | Implemented and tested |
| Data Integrity `eddsa-rdfc-2022`, JSON-LD in safe mode, RDFC-1.0 canonicalization | Implemented and tested against the W3C vectors |
| Baking into PNG (`iTXt`) and SVG, reading back | Implemented, tested and fuzzed |
| Revocation and suspension through a Bitstring Status List | Implemented and tested |
| Endorsements, Comprehensive Learner Record 2.0 | Implemented and tested |
| The 1EdTech conformance validator | Not run yet: it is part of the release |
| Open Badges 2.0, `eddsa-jcs-2022`, ECDSA cryptosuites, the 1EdTech revocation list | Not started |
| A Symfony bundle: the issuer's profile and keys served, the status lists, a verification page | Implemented in `phil/open-badges-bundle` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the keys. |
| [Issuing](doc/issuing.md) | Issuers, achievements, recipients, credentials. |
| [Securing](doc/securing.md) | JSON Web Tokens and Data Integrity proofs, the profile document. |
| [Verifying](doc/verifying.md) | `CredentialVerifier`, verification methods, the recipient, the JSON-LD subset. |
| [Baking](doc/baking.md) | A credential in a PNG or an SVG image. |
| [Revocation](doc/revocation.md) | Status entries, status lists and their credential. |
| [Endorsements and learner records](doc/endorsements-and-clr.md) | `EndorsementCredential` and CLR 2.0. |
| [Extension points](doc/extension-points.md) | Signers, verification methods, context loaders. |
| [Exceptions](doc/exceptions.md) | `OpenBadgesException` and its three exceptions. |
| [Testing](doc/testing.md) | The library's tests, vectors, fuzz targets and benchmark. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/open-badges
```

```php
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Recipient;

$signer = Ed25519Signer::fromSeed($seed);              // 32 secret bytes
$issuer = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $signer, 'https://example.org/issuer#key-1');
$achievement = new Achievement('https://example.org/badges/mentor', 'Mentor', 'Guided five newcomers.', 'Guide five newcomers through their first month.');
$recipient = Recipient::email($emailTheyGave, bin2hex(random_bytes(8)));

$token = $issuer->issue($achievement, $recipient, 'urn:uuid:'.$uuid, $awardedAt);     // VC-JWT

$document = new EddsaRdfc2022()->sign(                                                // Data Integrity
    $issuer->credential($achievement, $recipient, 'urn:uuid:'.$uuid, $awardedAt),
    $signer,
    'https://example.org/issuer#key-1',
    $now,
);
```

## Requirements

PHP 8.5 or later with the `dom`, `json`, `libxml`, `mbstring`, `openssl`, `sodium` and `zlib` extensions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE). The bundled JSON-LD contexts keep their own notices: see [NOTICE](NOTICE).
