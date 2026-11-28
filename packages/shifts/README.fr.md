<!-- phil-dev source-sha256=89c69fcc2e3c4d7b581557e814857ac6f72b46d284ace8cfc2bd09e4f6a619f2 -->
# phil/shifts

**Des quarts de bénévolat sans double réservation : quarts fixes, par tâche ou par durée, inscription, affectation ou attribution selon l'intérêt, une réserve et un tableau d'échange.**

phil/shifts est une bibliothèque PHP simple. Elle tient les **quarts** d'un événement et la **liste** de chacun : qui
occupe une place, qui attend dans la réserve, qui est intéressé. Un quart demande à un bénévole d'être présent du début
à la fin (**fixe**), d'accomplir une **tâche** à un moment de sa fenêtre, ou de servir une **durée** à l'heure de son
choix dans la fenêtre.

Ses places se donnent selon **trois modes** : les bénévoles **s'inscrivent** eux-mêmes, premier arrivé, premier servi ;
un coordonnateur **affecte** chaque place ; ou les bénévoles se disent **intéressés** et les places sont **attribuées**
parmi eux, par un coordonnateur ou par un attributeur qui les donne d'abord à ceux qui détiennent le moins de minutes.
Un coordonnateur peut affecter une place dans tous les modes. Un quart complet remplit sa **réserve**, et une place
libérée va au premier bénévole de la réserve.

Un quart peut exiger un **rôle** — premiers soins, caisse —, être **critique**, de sorte qu'un écran le montre en
premier tant qu'il manque de bénévoles, et **pondérer** ses minutes — un quart de nuit compté à 150 %. Deux quarts fixes
qui se chevauchent, ou qui sont plus proches que le repos demandé entre eux, sont signalés avant qu'un bénévole prenne
les deux.

Un bénévole qui ne peut pas venir met sa place sur le **tableau d'échange** ; un autre la prend, tout de suite, ou une
fois qu'un coordonnateur approuve, quand le quart l'exige. Une offre dont le bénévole n'occupe plus la place est
périmée, et personne ne la prend.

Les listes et les offres sont **immuables** : chaque opération retourne la suivante avec les **événements** du
changement, qui ne portent que des identifiants, ou refuse avec une raison qu'un écran traduit. Les **minutes servies**,
simples et pondérées, se lisent dans les listes des quarts terminés. Les quarts d'un événement circulent dans le format
JSON `phil-shifts/1`, lu dans des limites et réécrit à l'identique.

## État

| Partie | État |
|---|---|
| Quarts : fixes, par tâche, par durée ; places, réserve, rôle, critique, pondération | Implémenté et testé |
| Listes : inscription, affectation, intérêt, désistement et promotion depuis la réserve | Implémenté et testé |
| `FairAllocator`, `Conflicts` et `ServedHours` | Implémenté et testé |
| Le tableau d'échange : offrir, prendre, approuver, refuser, annuler, offres périmées | Implémenté et testé |
| Le format `phil-shifts/1` et les listes et offres stockées | Implémenté et testé |
| Quarts récurrents, attestation imprimable d'heures | Non écrit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et une première liste, de l'inscription aux heures servies. |
| [Quarts et listes](doc/fr/shifts-and-rosters.md) | Les types, les modes, la réserve, les conflits, l'attribution et les heures. |
| [Tableau d'échange](doc/fr/swap-board.md) | Offrir une place, la prendre, l'approbation du coordonnateur, les offres périmées. |
| [Format du plan](doc/fr/plan-format.md) | Le format JSON `phil-shifts/1`, ses limites, et les listes et offres stockées. |
| [Points d'extension](doc/fr/extension-points.md) | L'attributeur, le stockage, les événements, et ce qui est stable. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/shifts
```

```php
use Phil\Shifts\Hours\ServedHours;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;

$shift = new Shift('desk-sat-am', 'Info desk', ShiftKind::Fixed, new DateTimeImmutable('2026-11-28 09:00', $zone), new DateTimeImmutable('2026-11-28 12:00', $zone), capacity: 2, reserve: 3);
$roster = Roster::open($shift)->signUp('ada', $roles, $now);   // a place, or the reserve once the shift is full
$roster = $roster->withdraw('ada', $later);                   // the first of the reserve takes the place
$hours = ServedHours::of('bob', [$roster], $afterTheEvent);   // 180 minutes, 180 weighted
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
