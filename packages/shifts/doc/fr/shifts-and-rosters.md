<!-- phil-dev source-sha256=2333ab74952c8218c16783d26b8ea6bf98e63a70b92adb9a8d67475ff9b80279 -->
# Quarts et listes

## Les types de quarts

| Type | Demande à un bénévole | Compte | Se ferme |
|---|---|---|---|
| `Fixed` | d'être présent du début à la fin | les minutes de sa fenêtre, 1 440 au plus | à son début |
| `Task` | d'accomplir une tâche à un moment de la fenêtre | les minutes estimées, de 5 à 1 440 | à la fin de sa fenêtre |
| `Duration` | de servir un nombre de minutes à l'heure de son choix dans la fenêtre | ces minutes, de 5 à 1 440 | à la fin de sa fenêtre |

Une fenêtre dure de 5 minutes à 31 jours. Les minutes d'une tâche ou d'une durée tiennent dans sa fenêtre. Un quart
construit hors de ses limites lance `InvalidShift`, dont le message nomme le champ, jamais sa valeur.

| Champ | Limites | Valeur par défaut |
|---|---|---|
| `id` | de 1 à 64 lettres minuscules, chiffres, `_`, `.` et `-`, commençant par une lettre ou un chiffre | — |
| `title` | de 1 à 200 caractères | — |
| `capacity` | de 1 à 1 000 places | 1 |
| `reserve` | de 0 à 100 bénévoles | 0 |
| `mode` | `SelfSignUp`, `Assigned`, `Interest` | `SelfSignUp` |
| `requiredRole` | de 1 à 64 lettres, chiffres, `_`, `.`, `:` et `-` | aucun |
| `critical` | montré en premier tant qu'il manque de bénévoles | `false` |
| `weight` | de 10 à 1 000 pour cent de ses minutes | 100 |
| `swapApproval` | un échange attend un coordonnateur | `false` |
| `location`, `description` | 200 et 2 000 caractères | vide |

Les instants sont gardés en UTC, quel que soit le fuseau horaire dans lequel ils ont été donnés.

## Les trois modes

| Mode | `signUp()` | `expressInterest()` | `assign()` | `allocate()` |
|---|---|---|---|---|
| `SelfSignUp` | une place, puis la réserve | refusé | un coordonnateur | refusé |
| `Assigned` | refusé | refusé | un coordonnateur | refusé |
| `Interest` | refusé | la liste des intéressés | un coordonnateur | l'attributeur |

L'`assign()` d'un coordonnateur fonctionne dans tous les modes et ne vérifie pas les rôles : le coordonnateur décide. Un
bénévole de la réserve ou des intéressés qui est affecté passe à une place. Une opération refusée lance `ShiftRefused`
avec sa `reason` :

| Raison | Quand |
|---|---|
| `shift.wrong_mode` | l'opération n'est pas de celles du mode du quart |
| `shift.closed` | un quart fixe a commencé, ou la fenêtre d'une tâche ou d'une durée est terminée |
| `shift.role_required` | le bénévole n'a pas le rôle qu'exige le quart |
| `shift.already_on_roster` | le bénévole figure déjà sur la liste |
| `shift.full` | aucune place, et aucune place dans la réserve ni parmi les 1 000 intéressés |
| `shift.not_on_roster` | le désistement d'un bénévole qui n'y est pas |
| `shift.not_confirmed` | le transfert ou l'offre d'échange d'un bénévole qui n'occupe aucune place |

## La réserve

Une inscription à un quart complet attend dans sa réserve, dans l'ordre d'arrivée, tant que la réserve a de la place.
Quand un bénévole qui occupe une place se désiste — ou qu'un coordonnateur le retire —, le premier de la réserve prend
la place, et le changement contient `VolunteerWithdrew` puis `ReservePromoted`. Un bénévole de la réserve qui se désiste
la quitte, et personne ne bouge.

## L'attribution parmi les intéressés

`allocate()` donne les places libres d'un quart du mode `Interest` aux bénévoles qu'un `InterestAllocator` choisit. La
liste ne garde que les bénévoles retournés qui sont intéressés, une fois chacun, et pas plus que les places ; les autres
restent intéressés. `FairAllocator` choisit d'abord ceux qui détiennent le moins de minutes — les minutes qu'une
application lit dans les autres listes avec `ServedHours::held()` —, puis ceux qui ont exprimé leur intérêt les premiers,
puis par identifiant, de sorte que la même liste attribue toujours de la même façon.

```php
use Phil\Shifts\Allocation\FairAllocator;
use Phil\Shifts\Hours\ServedHours;

$roster = $roster->allocate(new FairAllocator(), ServedHours::held($otherRosters), $now);
```

## Les conflits

`Conflicts` énumère les quarts qu'un bénévole détient et qu'un nouveau chevaucherait : deux quarts fixes dont les
fenêtres se touchent, ou sont plus proches que le repos demandé entre eux, de 0 à 1 440 minutes. Un quart qui se termine
à 12:00 et un autre qui commence à 12:00 ne se chevauchent pas sans repos. Une tâche ou une durée se sert à l'heure que
choisit le bénévole, et ne chevauche rien. La liste ne voit pas les autres quarts ; l'application demande avant une
inscription, une affectation ou un échange :

```php
use Phil\Shifts\Roster\Conflicts;

if ([] !== new Conflicts(restMinutes: 30)->of($shift, $shiftsTheVolunteerHolds)) {
    // refuse, or tell the coordinator
}
```

## Un quart corrigé

`withShift()` donne à une liste son quart corrigé — un nouveau titre, plus de places, une autre fenêtre — avec les mêmes
bénévoles. Une capacité ou une réserve plus petite que ceux qui s'y trouvent, ou un autre identifiant, lance
`InvalidShift`.

## Les heures servies

`ServedHours::of()` lit les listes des quarts terminés — une fois leur fenêtre finie — sur lesquelles le bénévole
occupait une place : les minutes, les minutes pondérées, arrondies à la minute supérieure à partir de la demie, et les
quarts comptés. Une attestation d'heures de bénévolat ou une récompense par seuils les lit.

## Les événements

Chaque opération retourne la liste suivante avec les événements de ce changement dans `$changes`, qui ne portent que les
identifiants du quart et du bénévole :

| Événement | Quand |
|---|---|
| `VolunteerConfirmed` | une place prise : inscrit, affecté — avec le coordonnateur dans `assignedBy` —, ou attribué |
| `VolunteerReserved` | une inscription attend dans la réserve |
| `InterestExpressed` | un bénévole est intéressé |
| `VolunteerWithdrew` | un bénévole a quitté le quart |
| `ReservePromoted` | le premier de la réserve a pris une place libérée |
| `PlaceTransferred` | une place est passée par le tableau d'échange, avec le bénévole qui l'a donnée dans `fromVolunteerId` |
