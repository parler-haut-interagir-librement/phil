<!-- phil-dev source-sha256=dbe50e4f2374e6604ae8556a7a2928602619c3e5bcddec822164239686d52f34 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Classes en lecture seule, constantes typées, `array_all()`, `new` sans parenthèses. |
| `ext-mbstring` | toutes | Les titres et les lieux se comptent en caractères, pas en octets. |
| `psr/clock` | `^1.0` | Le `Responder` lit l'instant d'une réponse ou d'une décision sur une horloge PSR-20. |

## Installer

```sh
composer require phil/listings
```

## Déclarer les genres

Chaque genre d'annonce qu'offre l'application a un `KindPolicy` ; `KindPolicies` les contient, chaque genre une seule
fois.

```php
use Phil\Listings\KindPolicies;
use Phil\Listings\KindPolicy;
use Phil\Listings\ListingKind;
use Phil\Listings\Moderation;

$policies = new KindPolicies([
    KindPolicy::of(ListingKind::of('classified')),
    KindPolicy::of(ListingKind::of('job'), lifetimeDays: 60, moderation: Moderation::BeforePublication, maxOpenResponses: 500),
    KindPolicy::of(ListingKind::of('partner'), minimumAge: 13, reportsToHide: 2),
]);
```

| Règle | Valeur par défaut | Bornes | Rôle |
|---|---|---|---|
| `lifetimeDays` | 30 | 1 à 365 | La durée de publication d'une annonce, à partir de sa publication. |
| `reminderDays` | 3 | 0 à `lifetimeDays` − 1 | Le nombre de jours avant l'expiration où l'auteur reçoit un rappel ; 0 pour aucun rappel. |
| `extensionDays` | 30 | 1 à 365 | Le nombre de jours qu'ajoute une prolongation. |
| `maxExtensions` | 3 | 0 à 52 | Le nombre de fois qu'une annonce peut être prolongée. |
| `moderation` | `AfterPublication` | | Modérée avant qu'une annonce soit montrée, ou après, sur signalement. |
| `reportsToHide` | 3 | 1 à 100 | Le nombre de signalements de membres distincts qui masque une annonce publiée. |
| `minimumAge` | aucun | 0 à 120 | L'âge le plus jeune qui peut répondre ; une annonce peut en demander un plus élevé. |
| `acceptsResponses` | oui | | Si les annonces du genre acceptent des réponses. |
| `maxOpenResponses` | 100 | 1 à 10 000 | Le nombre de réponses en attente et acceptées que prend une annonce. |

Une valeur hors de ses bornes lance `InvalidListing`, de sorte qu'une politique lue dans la configuration échoue au
démarrage de l'application, et non quand un membre publie.

## Construire le répondeur

```php
use Phil\Listings\Responder;

$responder = new Responder($policies, $blockList, $memberAges, $responseStore, $clock);
```

La liste de blocage, l'âge des membres et le stockage des réponses appartiennent à l'application ([Points
d'extension](extension-points.md)). Le répondeur ne garde aucun état propre entre les appels : il peut être partagé sans
risque entre les requêtes d'un *worker* de longue durée.
