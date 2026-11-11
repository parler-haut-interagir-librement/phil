<!-- phil-dev source-sha256=9eb052638b088dfc0db647b3c40cafa0c9a88c04c8d95c8ada2e2ee6d70ace24 -->
# phil/open-badges

**Des Open Badges 3.0 que tout le monde peut vérifier : émis, signés, intégrés à l'image, révoqués, appuyés et
réunis dans un relevé d'apprentissage.**

phil/open-badges émet des titres Open Badges 3.0 — un `OpenBadgeCredential` dans le modèle de données de Verifiable
Credentials 2.0 — et les vérifie. Un titre affirme qu'un émetteur reconnaît une réalisation d'un destinataire. Il est
signé par la clé de l'émetteur : le destinataire peut ainsi l'emporter partout — un portfolio, un profil
professionnel, un portefeuille — et tout le monde peut le vérifier avec la clé publique de l'émetteur, sans
interroger le site qui l'a émis.

Il s'adresse à toute application PHP qui reconnaît ce que font les gens : une plateforme de formation qui décerne des
certificats, une communauté qui remercie ses bénévoles, un événement qui reconnaît ses conférenciers. La bibliothèque
ne lit aucun compte et ne stocke rien : l'application dit qui est le destinataire, tel qu'il se présente, et garde ce
qu'elle a émis.

C'est une simple bibliothèque PHP qui n'exige que PHP et ses extensions. Un titre est sécurisé de
l'une des deux façons que reconnaît Open Badges 3.0 : comme un JSON Web Token (`EdDSA` ou `RS256`), ou avec une preuve
Data Integrity intégrée de la suite cryptographique `eddsa-rdfc-2022`. Pour celle-ci, la bibliothèque a son propre
processeur JSON-LD et sa propre canonicalisation RDF. La vérification ne télécharge jamais rien : les contextes
JSON-LD sont inclus, et les clés sont celles que donne l'application.

## État

| Partie | État |
|---|---|
| Émetteurs, réalisations, destinataires (DID, adresse courriel sous forme d'empreinte ou URL), `OpenBadgeCredential` | Implémenté et testé |
| VC-JWT avec `EdDSA` (Ed25519) et `RS256` (RSA de 2048 bits ou plus) | Implémenté et testé |
| Data Integrity `eddsa-rdfc-2022`, JSON-LD en mode sûr, canonicalisation RDFC-1.0 | Implémenté et testé selon les vecteurs du W3C |
| Intégration dans une image PNG (`iTXt`) et SVG, relecture | Implémenté, testé et soumis au fuzzing |
| Révocation et suspension par une Bitstring Status List | Implémenté et testé |
| Appuis, Comprehensive Learner Record 2.0 | Implémenté et testé |
| Le validateur de conformité de 1EdTech | Pas encore exécuté : il fait partie de la publication |
| Open Badges 2.0, `eddsa-jcs-2022`, suites cryptographiques ECDSA, la liste de révocation de 1EdTech | Non commencé |
| Un bundle Symfony : profil et clés de l'émetteur servis, listes d'état, page de vérification | Implémenté dans `phil/open-badges-bundle` |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les clés. |
| [Émettre](doc/fr/issuing.md) | Émetteurs, réalisations, destinataires, titres. |
| [Sécuriser](doc/fr/securing.md) | JSON Web Tokens et preuves Data Integrity, le document de profil. |
| [Vérifier](doc/fr/verifying.md) | `CredentialVerifier`, méthodes de vérification, le destinataire, le sous-ensemble de JSON-LD. |
| [Intégrer à l'image](doc/fr/baking.md) | Un titre dans une image PNG ou SVG. |
| [Révocation](doc/fr/revocation.md) | Entrées d'état, listes d'état et leur titre. |
| [Appuis et relevés d'apprentissage](doc/fr/endorsements-and-clr.md) | `EndorsementCredential` et CLR 2.0. |
| [Points d'extension](doc/fr/extension-points.md) | Signataires, méthodes de vérification, chargeurs de contextes. |
| [Exceptions](doc/fr/exceptions.md) | `OpenBadgesException` et ses trois exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses vecteurs, ses cibles de fuzzing et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

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

## Prérequis

PHP 8.5 ou plus récent avec les extensions `dom`, `json`, `libxml`, `mbstring`, `openssl`, `sodium` et `zlib`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE). Les contextes JSON-LD inclus gardent leurs propres avis : voyez [NOTICE](NOTICE).
