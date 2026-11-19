<!-- phil-dev source-sha256=cbbcbd034976decdcbe99b55c509951b7828244b97c1b63c2b8d8b61e53fcae9 -->
# Ressources

Une `BookableResource` est ce qui peut être réservé, avec ce qui décide de ses réservations. Chaque valeur est
vérifiée au moment où elle est réglée, par les types propres à `phil/reservation` : une valeur que la bibliothèque
refuse lève son `InvalidDefinition`, et la ressource garde la valeur qu'elle avait.

```php
$room = BookableResource::create('Salle Verne', capacity: 1, timezone: 'America/Toronto');
$room->buffer(before: 10, after: 15);
$room->restrict(minimumNotice: 120, maximumAdvance: 60 * 24 * 90, minimumDuration: 60, maximumDuration: 240, slotStep: 30);
$room->openOn([1 => [[540, 1260]], 2 => [[540, 1260]], 6 => [[600, 1440]], 7 => [[0, 60]]], closedDays: ['2026-12-25']);
$room->remindBefore(1440, 60);
$room->holdFor(15);
$resources->save($room);
```

| Méthode | Ce qu'elle règle |
|---|---|
| `create(name, capacity, timezone)` | Un nom de 1 à 200 caractères, les unités réservées à la fois, le fuseau horaire IANA dans lequel ses heures sont lues |
| `rename()`, `resize()`, `moveTo()` | Les mêmes, plus tard |
| `buffer(before, after)` | Les minutes retenues avant et après chaque réservation ; elles s'additionnent entre deux réservations |
| `restrict(…)` | Le préavis, la fenêtre de réservation, la durée la plus courte et la plus longue et la grille, en minutes ; `null` en écarte une |
| `openOn(hours, closedDays)` | Les heures hebdomadaires par jour de semaine ISO, en minutes de la journée, et les jours fermés ; `null` l'ouvre en tout temps |
| `remindBefore(…)` | Les minutes avant le début où chaque titulaire reçoit un rappel, 20 au plus |
| `holdFor(minutes)` | La durée pendant laquelle une retenue garde ses unités, d'une minute à un jour ; 15 par défaut |
| `activate(false)` | N'accepte plus de nouvelle réservation ; celles déjà faites restent |

Les heures suivent `WeeklyAvailability` de `phil/reservation` : des plages qui se touchent forment un seul intervalle,
y compris par-dessus minuit. `bookable()` donne le `Bookable` de la bibliothèque construit à partir de ces valeurs, et
`reminderSchedule()` son `ReminderSchedule`.

`claims()` compte les requêtes qui ont verrouillé la ligne de la ressource ; voir
[Réservations](reservations.md#le-verrou).

## Temps tampons entre voisines

Les temps tampons de deux réservations s'additionnent : avec quinze minutes après chaque réservation et quinze avant
chacune, une réservation qui se termine à 11 h 00 laisse la ressource libre à partir de 11 h 30, non de 11 h 15. Une
demande est vérifiée avec ses propres temps tampons contre les plages des autres, les leurs comprises.

## Personnes

Une personne — un maître de jeu, un conférencier, un bénévole, un enseignant — est une ressource comme une salle ou une
table : une `BookableResource` de capacité un, dans le fuseau horaire de la personne, dont les heures sont celles que la
personne donne. Ses réservations suivent les mêmes règles : une personne réservée à une table de 19 h 00 à 22 h 00 est
refusée à une autre à partir de 21 h 00, et un temps tampon après chaque réservation lui laisse une pause entre deux.

```php
$gameMaster = BookableResource::create('Game master 12', capacity: 1, timezone: 'America/Toronto');
$gameMaster->buffer(before: 0, after: 15);
$resources->save($gameMaster);

$reservations->book($gameMaster->id, $start, $end, holder: 'table-4');
```

La ressource désigne la personne par un identifiant ou un rôle, jamais par une adresse ; le lien entre la ressource et
le compte de la personne revient à l'application. Une séance qui a besoin d'une salle et d'une personne réserve les
deux, dans l'ordre que choisit l'application, et annule la première quand la seconde est refusée.
