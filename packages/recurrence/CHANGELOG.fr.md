<!-- phil-dev source-sha256=2264512e87b0b7b2a56f0c895783e4b9330dc2f681b10917f519600d701413f3 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/recurrence` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- Le README, en anglais et en français, dit ce que fait le paquet, sans la formulation de ses premières ébauches.
- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Frequency`, `Weekday`, `WeekdayNum`, `RecurrenceRule` ; `RecurrenceException` et `InvalidRule`.
  `RecurrenceRule::of()` refuse ce que `parse()` ne peut pas relire — un intervalle ou un compte au-dessus de
  `MAX_NUMBER`, 9999, une fin hors des années 1 à 9999 —, pour que chaque règle écrite en texte se relise.
- `Recurrence`, qui dispose une période avec ses jours comme nombres, ne construit une date que pour un jour qu'elle
  garde, et lit ses exceptions une fois. `between()` et `next()` atteignent la période de leur fenêtre par
  l'arithmétique quand la règle n'a pas de `COUNT`, et `between()` s'arrête à la période de son dernier moment.
  `MAX_PERIODS` ne compte que les périodes consécutives qui ne correspondent à aucun jour, si bien qu'une règle sans fin
  s'exécute tant qu'elle correspond. Les occurrences s'arrêtent à la fin de l'année 9999, une règle qui finit à son
  dernier jour (`UNTIL=99991231`) comprise. `between()` et `next()` ne parcourent pas plus de `MAX_WALKED` périodes par
  appel, pour qu'une règle qui correspond à un jour en plusieurs années coûte un temps borné. Les dates qu'ajoute
  `RDATE`, données à `of()`, sont fusionnées dans les occurrences dans l'ordre, une fois chacune, comptées ni dans
  `COUNT` ni dans `UNTIL`, et retirées par une exception comme toute occurrence ; les exceptions sont cherchées par
  instant dans un ensemble.
- `RecurrenceDates` : une ligne `RDATE` ou `EXDATE` lue — des jours entiers, des heures UTC, des heures avec `TZID`, des
  heures flottantes lues dans un fuseau donné, pliée ou non — et récrite ; les périodes sont refusées, et une ligne
  contient au plus `MAX_DATES` dates en `MAX_LENGTH` octets. Sa cible de fuzzing, `recurrence-dates`, vérifie qu'une
  ligne récrite se relit à l'identique.
- `SchemaOrg` : la forme schema.org de la valeur de la bibliothèque, à intégrer dans du JSON-LD ; elle écrit la
  `endDate` d'une heure de fin comme le jour où elle tombe dans le fuseau horaire de la première occurrence.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
