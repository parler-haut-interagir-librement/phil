<!-- phil-dev source-sha256=e57c72b8e941685b1093a98b033a276115281fd532f353cb196d76e3bcc82f7e -->
# Événements

`Habits` répartit des événements par le répartiteur PSR-14 qu'on lui donne, quand il y en a un. Chacun désigne
l'habitude et son membre — deux identifiants opaques —, et le jour ou le nombre atteint ; jamais le nom de l'habitude ni
une valeur validée.

| Événement | Réparti quand | Porte |
|---|---|---|
| `HabitChecked` | Un jour a été validé : fait, en partie, sauté ou gelé. | `habit`, `member`, `day` sous la forme `YYYY-MM-DD` |
| `HabitUnchecked` | La validation d'un jour a été annulée. | `habit`, `member`, `day` |
| `StreakReached` | Une validation a porté la série courante à l'une des longueurs de `Milestones`. | `habit`, `member`, `length`, `unit` |
| `StrengthReached` | Une validation a porté la force jusqu'à l'un des pourcentages de `Milestones`, ou au-delà. | `habit`, `member`, `percent` |

Tous sont dans `Phil\Habits\Event`. Après une validation, `HabitChecked` vient d'abord, puis les séries atteintes à
partir de la plus courte, puis les forces à partir de la plus basse. Une validation qui en remplace une autre du même
jour ne répartit rien pour un jalon déjà passé ; une validation annulée puis enregistrée de nouveau l'atteint de
nouveau, comme le fait la série. Une baisse de la force ne répartit rien : la bibliothèque ne dit jamais à un membre
qu'il a perdu quelque chose.

## Les jalons

`Milestones` contient les longueurs de série et les pourcentages de force à célébrer — par défaut 3, 7, 14, 30, 60, 100,
200 et 365 périodes, et 25, 50, 75 et 100 % —, jusqu'à cinquante de chaque sorte :

```php
new Milestones(streaks: [7, 21, 66], strengths: [50, 90]);
```

Une longueur de série compte des périodes de l'horaire de l'habitude : 7 est une semaine de jours pour une habitude
quotidienne, sept semaines pour une habitude due trois fois par semaine. L'`unit` de l'événement indique laquelle.

## Ludification

La bibliothèque n'attribue rien. Un paquet de ludification écoute ces événements et les transforme en points, en badges
ou en célébrations, selon ses propres règles ; un pont vers un grand livre de points vit dans ce paquet, jamais ici. Un
écouteur qui a besoin de plus que les identifiants lit l'habitude avec `Habits::get()` et son progrès avec
`progress()`.
