<!-- phil-dev source-sha256=f440c919da0b0696d5b8f986f00c9da5803b2d9b50d1fdcecdedab038b906efd -->
# Points de ramassage et plages

## Un point de ramassage

```php
use Phil\LocalPickup\PickupPoint;
use Phil\LocalPickup\PickupPointKind;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;
use Phil\Reservation\Rule\MaximumAdvance;

$zone = new DateTimeZone('America/Toronto');
$booth = new PickupPoint(
    code: 'booth-hall-b',
    name: 'Booth 214, Hall B',
    kind: PickupPointKind::ConventionBooth,
    stockLocation: 'convention-stock',
    address: "Convention centre\nHall B, booth 214",
    slots: new Bookable(
        capacity: 6,
        availability: new WeeklyAvailability([5 => [[600, 1140]], 6 => [[540, 1140]], 7 => [[540, 1020]]], $zone),
        rules: [new MaximumAdvance(60 * 24 * 30)],
        timezone: $zone,
    ),
    slotMinutes: 15,
    preparationMinutes: 60,
    holdHours: 48,
);
```

| Champ | Sens | Bornes |
|---|---|---|
| `code` | L'identifiant du point. | Jusqu'à 64 lettres minuscules, chiffres, `_`, `.` ou `-`. |
| `name`, `address` | Ce que montrent l'avis et le reçu ; l'adresse peut contenir des sauts de ligne. | De 1 à 500 octets d'UTF-8, aucun autre caractère de contrôle. |
| `kind` | `Store` ou `ConventionBooth`. | |
| `stockLocation` | Le code de l'emplacement de stock d'où viennent les marchandises, tel que le nomme la logistique de l'application. | Comme `code`. |
| `slots` | Un `Bookable` de phil/reservation : sa capacité est le nombre de commandes qu'une plage accepte ; ses heures, ses jours de fermeture, ses tampons et ses règles disent quand les clients peuvent venir ; son fuseau horaire est l'horloge du point. | |
| `slotMinutes` | La durée d'une plage. | De 1 à 1 440. |
| `preparationMinutes` | Le temps dont une commande a besoin avant d'être prête : aucune plage ne commence plus tôt après le passage de la commande. | De 0 à 43 200 (trente jours). |
| `holdHours` | Combien de temps une commande prête attend au comptoir. | De 1 à 2 160 (quatre-vingt-dix jours). |
| `accepting` | Si le point accepte de nouvelles commandes. | |

Un point est de la configuration : l'application le construit à partir de ses propres enregistrements, et la
bibliothèque ne le stocke jamais.

## Le point convenu

Passer une commande copie le point sur le ramassage sous la forme d'un `AgreedPickupPoint` : son code, son nom, son
genre, son emplacement de stock, son adresse, son fuseau horaire et son délai de garde. L'avis, le reçu et l'expiration
lisent cette copie. Quand le marchand renomme le point, le déplace, raccourcit son délai de garde ou cesse d'y prendre
des commandes — fermé pour la journée, ou n'ayant plus le droit d'y vendre —, les commandes déjà passées gardent le
comptoir qui leur a été promis et continuent d'être préparées, annoncées et remises. Seul `Pickups::place()` lit
`accepting`.

## Les plages

`PickupSlots::available()` donne les plages d'une période que le client peut choisir maintenant :

```php
$week = Interval::ofTimestamps($monday->getTimestamp(), $monday->modify('+7 days')->getTimestamp(), $zone);
$offered = $pickupSlots->available($booth, $week, new DateTimeImmutable(), limit: 100);
```

Chaque plage est ouverte, suit les règles du point, a encore une place parmi les ramassages en préparation ou prêts, et
commence au plus tôt après le temps de préparation à partir de maintenant. Les ramassages ramassés ou expirés libèrent
leur place. Les débuts suivent la grille de phil/reservation : toutes les `slotMinutes` à partir de minuit sur
l'horloge du point.

`PickupSlots::check()` donne la `Decision` de phil/reservation pour une plage : ses raisons — `Full`, `Unavailable`,
celles des règles — avec les commandes qu'elle rencontre comme conflits, et `TooSoon` avec
`['minutes' => preparationMinutes]` quand la commande ne pourrait pas être prête à son début. `Pickups::place()` refuse
une plage qui a une raison quelconque, et le `PickupRefused` qu'il lève porte les violations.

Deux clients qui prennent la dernière place en même temps lisent tous deux une place libre : l'application les place
l'un après l'autre avec un verrou sur le point autour de `Pickups::place()`, par exemple un verrou de symfony/lock dont
la clé est le code du point.
