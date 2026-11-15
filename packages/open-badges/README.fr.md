<!-- phil-dev source-sha256=60f436ef2e32638b8aa6c147da454f54354f15ab8f19299ca4967f5a7aa1d753 -->
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

C'est une simple bibliothèque PHP, qui repose sur les extensions de PHP, `phil/url` et `phil/safe-http`. Un titre est
sécurisé de l'une des deux façons que reconnaît Open Badges 3.0 : comme un JSON Web Token (`EdDSA` ou `RS256`), ou avec
une preuve Data Integrity intégrée de la suite cryptographique `eddsa-rdfc-2022`. Pour celle-ci, la bibliothèque a son
propre processeur JSON-LD et sa propre canonicalisation RDF. La vérification ne télécharge rien tant que l'application
ne le demande pas : les contextes JSON-LD sont inclus, et les clés sont celles que donne l'application, ou celles
qu'un émetteur `did:web` publie dans son document DID, lu dans des limites.

## État

| Partie | État |
|---|---|
| Émetteurs, réalisations, destinataires (DID, adresse courriel sous forme d'empreinte ou URL), `OpenBadgeCredential` | Implémenté et testé |
| VC-JWT avec `EdDSA` (Ed25519) et `RS256` (RSA de 2048 bits ou plus), clés lues en PEM, en JSON Web Keys et en Multikeys | Implémenté et testé |
| Émetteurs identifiés par `did:web` : leur document DID lu par `phil/safe-http`, ses clés vérifiées | Implémenté, testé et soumis au fuzzing |
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
| [Vérifier](doc/fr/verifying.md) | `CredentialVerifier`, méthodes de vérification, émetteurs `did:web`, le destinataire, le sous-ensemble de JSON-LD. |
| [Intégrer à l'image](doc/fr/baking.md) | Un titre dans une image PNG ou SVG. |
| [Révocation](doc/fr/revocation.md) | Entrées d'état, listes d'état et leur titre. |
| [Appuis et relevés d'apprentissage](doc/fr/endorsements-and-clr.md) | `EndorsementCredential` et CLR 2.0. |
| [Points d'extension](doc/fr/extension-points.md) | Signataires, méthodes de vérification, lecteurs de documents DID, chargeurs de contextes. |
| [Exceptions](doc/fr/exceptions.md) | `OpenBadgesException` et ses trois exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses vecteurs, ses cibles de fuzzing et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |
| [Mise à niveau](doc/fr/upgrading.md) | Ce qui change d'une version à la suivante. |

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

PHP 8.5 ou plus récent avec les extensions `dom`, `json`, `libxml`, `mbstring`, `openssl`, `sodium` et `zlib` ; les jetons et les preuves sont signés et vérifiés par
`web-token/jwt-library`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE). Les contextes JSON-LD inclus gardent leurs propres avis : voyez [NOTICE](NOTICE).
