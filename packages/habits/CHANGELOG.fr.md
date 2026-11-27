<!-- phil-dev source-sha256=768a2718520933f9ccebdf507740fa6fd5e2a9612a6e6c332f2424e08ed320f2 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/habits` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.1-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/dbal` 4.6).

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `Habit`, une habitude d'un membre nommé par un identifiant opaque, jamais une adresse courriel, avec son horaire, son
  fuseau horaire, son premier jour, sa cible numérique facultative et son dernier jour une fois archivée.
- `Schedule` et `Frequency` : chaque jour, une fois tous les N jours, certains jours de la semaine, N fois par semaine
  ou par mois ; `parse()` et `toString()` de leurs règles, et `periods()`, les `Period` qu'ils découpent à partir du
  premier jour de l'habitude.
- `Day`, un jour du calendrier gardé comme son nombre de jours depuis le 1970-01-01, lu de `YYYY-MM-DD` ou d'un instant
  dans un fuseau horaire.
- `CheckIn` et `CheckInStatus` : fait, fait en partie vers la cible, sauté ou gelé, consigné en UTC.
- `ProgressCalculator`, `Progress`, `PeriodProgress`, `Streak` et `StreakUnit` : les séries, courante et meilleure,
  qu'épargnent les jours excusés et la période en cours, et la force de 0 à 1, montrée en pourcentage entier.
- `HalfLifeStrength`, une force lissée exponentiellement dont la mémoire diminue de moitié tous les quatorze jours,
  derrière le port `StrengthModel`.
- `Habits` : `start()`, `get()`, `today()`, `change()`, `archive()`, `checkIn()`, `freeze()`, `undo()`, `progress()`,
  `history()`, `ofMember()`, `remove()` et `forgetMember()`, avec les jours sur lesquels un pointage peut revenir.
- `Milestones`, et les événements `HabitChecked`, `HabitUnchecked`, `StreakReached` et `StrengthReached`, qui nomment
  l'habitude et son membre.
- Les ports `HabitStore` et `CheckInStore` ; des stockages en mémoire, et `DbalHabitStore` et `DbalCheckInStore` sur
  Doctrine DBAL avec `configureSchema()`, testés sur SQLite, MySQL 8.4 et PostgreSQL 17.
- `HabitsException`, implémentée par `InvalidHabit`, `InvalidSchedule`, `InvalidCheckIn`, `InvalidDay` et
  `HabitNotFound`.
- Des cibles de fuzzing pour les règles d'horaire et les jours, et un banc d'essai de la progression sur dix ans.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
