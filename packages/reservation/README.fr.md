<!-- phil-dev source-sha256=83299dd85fcc9662bc07a468a0d9b47290c4429028030f754180bec912a7b79b -->
# phil/reservation

**Le cœur des réservations : une ressource, ses créneaux, ses règles, ses conflits et ses rappels.**

phil/reservation décide si quelque chose peut être réservé. Une ressource — une salle, une table et ses places, le
temps d'une personne, un équipement, un lit, un jeu — a une capacité, des marges autour de chaque réservation pour
installer et ranger, des heures d'ouverture et les règles que suit une réservation : un préavis, une fenêtre de
réservation, une durée minimale et maximale, une grille de débuts. Une demande est vérifiée par rapport aux
réservations déjà faites et reçoit chaque raison pour laquelle elle est refusée, avec les réservations qu'elle croise.
La même ressource liste les créneaux libres d'une période, et un calendrier de rappels dit quel rappel d'une
réservation est dû.

Elle s'adresse à toute application PHP qui réserve quelque chose : des salles et des tables directement, et, par-dessus
en couches minces, des rendez-vous, la location d'équipement, l'hébergement, les prêts d'une ludothèque ou les sessions
d'un jeu d'évasion. La bibliothèque ne dépend que de PHP et de `ext-mbstring` ; elle ne garde
aucun état et n'enregistre rien. Dans une application Symfony, `phil/reservation-bundle` garde les ressources et les
réservations dans Doctrine, les retient et les confirme une requête à la fois par ressource, envoie les rappels et
montre les réservations dans le calendrier.

## État

| Partie | État |
|---|---|
| `Interval`, `Booking`, `BookingRequest`, `Decision`, `Violation` | Implémenté et testé |
| `Bookable` : vérification d'une demande, unités libres, créneaux libres | Implémenté et testé |
| Règles : préavis, fenêtre de réservation, durées, grille | Implémenté et testé |
| Disponibilité : toujours ouvert, heures hebdomadaires avec jours fermés | Implémenté et testé |
| `ReminderSchedule` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Ressources](doc/fr/resources.md) | `Bookable` : capacité, marges, heures, règles, fuseau horaire. |
| [Vérifier une demande](doc/fr/checking.md) | `BookingRequest`, `Decision`, les raisons d'un refus, les conflits. |
| [Créneaux libres](doc/fr/slots.md) | `slots()` et `free()` : pas, quantité, bornes. |
| [Rappels](doc/fr/reminders.md) | `ReminderSchedule` : décalages, rappel dû, rappels dépassés. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données que l'application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, son seuil de mutation et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/reservation
```

```php
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;
use Phil\Reservation\Booking;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Interval;
use Phil\Reservation\Rule\MinimumNotice;
use Phil\Reservation\Rule\SlotGrid;

$zone = new DateTimeZone('America/Toronto');
$room = new Bookable(
    capacity: 1,
    bufferAfter: 15,
    availability: new WeeklyAvailability([1 => [[540, 1020]], 2 => [[540, 1020]]], $zone),
    rules: [new MinimumNotice(120), new SlotGrid(30)],
    timezone: $zone,
);

$booked = [new Booking('b-1', new Interval(new DateTimeImmutable('2026-11-10 10:00', $zone), new DateTimeImmutable('2026-11-10 11:00', $zone)))];
$asked = new Interval(new DateTimeImmutable('2026-11-10 11:00', $zone), new DateTimeImmutable('2026-11-10 12:00', $zone));

$decision = $room->check(new BookingRequest($asked, new DateTimeImmutable()), $booked);
$decision->allowed();          // false: the buffer after b-1 holds the room until 11:15
$decision->violations[0]->code; // ViolationCode::Full
$decision->conflicts;           // ['b-1']

$room->slots(new Interval(new DateTimeImmutable('2026-11-10 09:00', $zone), new DateTimeImmutable('2026-11-10 17:00', $zone)), 60, $booked, new DateTimeImmutable());
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
