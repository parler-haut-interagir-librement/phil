<!-- phil-dev source-sha256=44995f6341f2eb4546098270e79d7278bbb5fcb02053e405d44c8ba751c8a842 -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`.

## Installer

```sh
composer require phil/shifts
```

La bibliothèque n'a ni configuration ni service : une application construit ses objets et stocke ce qu'écrivent les
formats, ou utilise phil/shifts-bundle, qui stocke les listes et le tableau d'échange avec Doctrine et les montre dans
Twig.

## Une première liste

Un quart fixe de deux places et d'une réserve d'une personne, un bénévole qui s'inscrit, un troisième qui attend, un
désistement qui promeut la réserve, et les minutes servies une fois le quart terminé :

```php
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Hours\ServedHours;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;

$zone = new DateTimeZone('America/Toronto');
$shift = new Shift(
    'desk-sat-am',
    'Info desk',
    ShiftKind::Fixed,
    new DateTimeImmutable('2026-11-28 09:00', $zone),
    new DateTimeImmutable('2026-11-28 12:00', $zone),
    capacity: 2,
    reserve: 1,
);

$roster = Roster::open($shift)
    ->signUp('ada', [], new DateTimeImmutable('2026-11-20 10:00', $zone))
    ->signUp('bob', [], new DateTimeImmutable('2026-11-20 10:05', $zone))
    ->signUp('cy', [], new DateTimeImmutable('2026-11-20 10:10', $zone));   // the reserve

$roster = $roster->withdraw('ada', new DateTimeImmutable('2026-11-27 18:00', $zone));
// $roster->changes: VolunteerWithdrew(ada), ReservePromoted(cy)

try {
    $roster->signUp('dee', [], new DateTimeImmutable('2026-11-28 09:30', $zone));
} catch (ShiftRefused $refused) {
    $refused->reason;   // 'shift.closed': the shift has started
}

ServedHours::of('cy', [$roster], new DateTimeImmutable('2026-11-28 12:00', $zone))->minutes;   // 180
```

Les instants qu'une application donne sont ceux de sa propre horloge ; la bibliothèque les lit en UTC et ne demande
jamais l'heure au système.
