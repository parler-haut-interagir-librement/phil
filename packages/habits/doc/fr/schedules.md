<!-- phil-dev source-sha256=acce69ca48b19a52adfe644840a661f203048c0809ca3054d950f26528e0612b -->
# Horaires

Un horaire dit quand une habitude est due. Il découpe les jours de l'habitude, à partir de son premier, en périodes,
chacune demandant un nombre de validations. La série compte des périodes ; la force bouge une fois par période.

## Les sortes

| Sorte | Règle | Constructeur | Périodes | Une série compte |
|---|---|---|---|---|
| Chaque jour | `daily` | `Schedule::daily()` | Chaque jour, une validation. | Des jours |
| Une fois tous les N jours | `every 3 days` | `Schedule::everyDays(3)` | Chaque fenêtre de N jours à partir du premier jour de l'habitude, une validation un jour quelconque de la fenêtre. N va de 2 à 365. | Des fenêtres |
| Certains jours de la semaine | `on mon,wed,fri` | `Schedule::onWeekdays([1, 3, 5])` | Chacun de ces jours, une validation ; les autres jours ne demandent rien. | Des jours |
| N fois par semaine | `3 per week` | `Schedule::perWeek(3)` | Chaque semaine ISO, du lundi au dimanche, N validations. N va de 1 à 7. | Des semaines |
| N fois par mois | `10 per month` | `Schedule::perMonth(10)` | Chaque mois du calendrier, N validations. N va de 1 à 31. | Des mois |

Une fenêtre de N jours est indulgente exprès : « tous les 3 jours » est respecté par une validation l'un quelconque des
trois jours, non un jour fixe.

## Les règles

`Schedule::parse()` lit une règle en n'importe quelle casse, ses mots séparés par des espaces ou des tabulations ; les
jours de la semaine s'écrivent de `mon` à `sun`, séparés par des virgules, des espaces ou les deux. `toString()`
réécrit la règle sous sa forme canonique, qui est celle que gardent les dépôts en base de données :

```php
Schedule::parse(' On FRI, mon ')->toString();   // "on mon,fri"
Schedule::parse('10 PER MONTH')->toString();    // "10 per month"
```

Une règle compte au plus 64 octets. Les nombres sont des entiers sans zéros initiaux ni signes. Tout le reste est
refusé avec `InvalidSchedule`, dont le message dit à quoi ressemble une règle ; l'analyseur a une cible de fuzzing qui
l'y tient.

## Les périodes

`periods($first, $through)` donne les périodes du premier jour d'une habitude jusqu'à un jour, dans l'ordre, la dernière
contenant ce jour. Une période coupée par le premier jour ne demande pas plus de validations qu'elle n'a de jours : une
habitude due 3 fois par semaine, commencée un samedi, demande 2 validations pour sa première semaine. Chaque période
porte aussi le nombre de jours qu'elle représente dans la force — sa longueur, ou sept divisé par le nombre de jours
dus pour une habitude due certains jours de la semaine — : voir [Force](strength.md).

Une habitude dont l'horaire change garde ses validations ; son progrès est recalculé selon le nouvel horaire à partir de
son premier jour.

## Les jours et les fuseaux horaires

Les jours d'une habitude se vivent dans son fuseau horaire. `Day` est un jour du calendrier sans heure ni fuseau,
gardé comme son nombre de jours depuis le 1970-01-01, de sorte que les jours se comparent comme des entiers ;
`Day::of($instant, $zone)` donne le jour où tombe un instant dans ce fuseau. `Habits` demande l'instant à l'horloge et
le fuseau à l'habitude : pour un membre à Toronto, 03 h 30 UTC le 2 novembre est encore le 1er novembre.
