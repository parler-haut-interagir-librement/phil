<!-- phil-dev source-sha256=7263adc8ef2d8d544adecd5560e92de686fa5b5abe9eecc9b8ebe2eef262814e -->
# phil/listings

**Le noyau des annonces : une petite annonce, une offre d'emploi, une recherche de partenaires, rédigées, modérées,
reçoivent des réponses et expirent selon les mêmes règles.**

phil/listings est la partie que partagent tous les genres d'annonces. Une annonce a un titre, des critères typés
auxquels une recherche peut correspondre, une zone montrée à tous avec un lieu exact montré seulement aux personnes que
son auteur accepte, et un âge minimal. Elle est publiée tout de suite ou après l'approbation d'un modérateur, masquée
par un nombre suffisant de signalements, rappelée avant son expiration, prolongée un nombre limité de fois et fermée par
son auteur. Les membres y répondent ; une réponse ne porte aucun message, et seule une réponse que l'auteur accepte
permet à l'application de mettre les deux en contact, avec le consentement de chacun.

Elle s'adresse à toute application PHP où des membres publient des offres ou des demandes les uns pour les autres : de
petites annonces entre membres, les offres d'emploi d'une communauté, des personnes qui cherchent des partenaires pour
une activité, une place de marché entre membres et vendeurs. Chacun d'eux est un genre d'annonce avec son propre
`KindPolicy` (durée de vie, rappel, prolongations, modération, signalements, âge minimal, réponses ouvertes) et ses
propres champs et écrans, construits sur ce noyau.

C'est une simple bibliothèque PHP dont les prérequis sont `ext-mbstring` et `psr/clock`. Les annonces
et les réponses sont immuables : chaque transition prend la politique du genre de l'annonce et un instant, et retourne
une nouvelle valeur. Toute la vie d'une annonce se teste donc à la seconde près et se stocke sous forme de valeurs
simples. Ce que la bibliothèque ne peut pas savoir est un port : qui a bloqué qui (`BlockList`), quel âge a un membre
(`MemberAges`) et où vivent les réponses (`ResponseStore`). Chaque valeur est bornée (titres, libellés, critères, choix,
jours, signalements, réponses), de sorte que rien de ce qu'un membre tape ou de ce que dit une configuration ne peut
rendre une annonce illimitée.

## État

| Partie | État |
|---|---|
| Identifiants, genres, motifs de modération, titres, lieux | Implémenté et testé |
| Critères (choix, plages d'entiers, indicateurs) et leur correspondance avec une recherche | Implémenté et testé |
| Politiques de genre, bornées, et leur registre | Implémenté et testé |
| La vie d'une annonce : brouillon, soumission, modération avant ou après publication, signalements, modifications, expiration, rappels, prolongations, fermeture, retrait | Implémenté et testé |
| Réponses (en attente, acceptée, déclinée, retirée) et les règles du `Responder` : visibilité, annonce de l'auteur, blocages, âge minimal, une réponse ouverte par membre, réponses ouvertes par annonce | Implémenté et testé |
| Persistance, formulaires, écrans, notifications, conversations, paiement | Pas dans cette bibliothèque : ils relèvent de l'application ou d'un bundle construit sur elle |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la déclaration des genres. |
| [Annonces](doc/fr/listings.md) | Les titres, les lieux, l'âge minimal, les statuts et chaque transition. |
| [Critères](doc/fr/criteria.md) | Les choix, les plages et les indicateurs, et la façon dont une recherche correspond à une annonce. |
| [Modération et expiration](doc/fr/moderation-and-expiry.md) | La modération avant ou après publication, les signalements, l'expiration, les rappels et les prolongations. |
| [Réponses](doc/fr/responses.md) | Répondre, accepter, décliner, retirer, et les règles du `Responder`. |
| [Points d'extension](doc/fr/extension-points.md) | La liste de blocage, l'âge des membres et le stockage des réponses. |
| [Exceptions](doc/fr/exceptions.md) | `ListingsException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, son seuil de mutation et son banc d'essai, et comment tester du code construit sur elle. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/listings
```

```php
use Phil\Listings\Criteria;
use Phil\Listings\Criterion;
use Phil\Listings\KindPolicies;
use Phil\Listings\KindPolicy;
use Phil\Listings\Listing;
use Phil\Listings\ListingId;
use Phil\Listings\ListingKind;
use Phil\Listings\Location;
use Phil\Listings\MemberId;
use Phil\Listings\Responder;
use Phil\Listings\ResponseId;
use Phil\Listings\Title;

$partners = KindPolicy::of(ListingKind::of('partner'), lifetimeDays: 30, minimumAge: 13);
$policies = new KindPolicies([$partners]);

$listing = Listing::draft(
    ListingId::of('01JC4Z9X8Y7W6V5T4S3R2Q1P0N'),
    $partners,
    MemberId::of('member-42'),
    Title::of('Two players wanted for a weekly campaign'),
    Criteria::of([Criterion::choice('day', ['sat']), Criterion::range('players', 2, 2)]),
    Location::area('Plateau', '4200 Saint-Laurent'),
    18,
    $clock->now(),
)->submit($partners, $clock->now());   // published at once: the kind is moderated after publication

$responder = new Responder($policies, $blockList, $memberAges, $responseStore, $clock);
$response = $responder->respond($listing, ResponseId::of('01JC5A…'), MemberId::of('member-7'));

$accepted = $responder->accept($listing, $response);   // both consent: the application may open a conversation
```

`$blockList`, `$memberAges` et `$responseStore` appartiennent à l'application : voir [Points
d'extension](doc/fr/extension-points.md).

## Prérequis

PHP 8.5 ou plus récent, `ext-mbstring` et `psr/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
