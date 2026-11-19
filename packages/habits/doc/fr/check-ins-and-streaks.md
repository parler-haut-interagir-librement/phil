<!-- phil-dev source-sha256=8e5bf8b701b7d92ed8181b5490948b9cab03b256ce908110a77312a6a1acdc55 -->
# Validations et séries

## Valider

`Habits::checkIn($id, $status, $value, $day)` enregistre ce que le membre a fait un jour — aujourd'hui quand aucun jour
n'est donné —, à la place de ce qui était enregistré ce jour-là : une habitude contient au plus une validation par jour.

| Statut | Signification | Valeur |
|---|---|---|
| `CheckInStatus::Done` | Fait, toute la cible quand l'habitude en a une. | Facultative, pour mémoire, sur une habitude avec cible. |
| `CheckInStatus::Partial` | Fait en partie, vers la cible numérique de l'habitude. | Obligatoire : la quantité faite. Seule une habitude avec cible l'accepte. |
| `CheckInStatus::Skipped` | Sauté exprès. | Aucune. |
| `CheckInStatus::Frozen` | Gelé d'avance : un voyage, une maladie, un jour de repos. | Aucune. |

Une habitude avec cible — 20 pages, 8 verres — compte ses jours en unités : un jour fait apporte toute la cible, un jour
partiel sa valeur jusqu'à la cible. Une habitude sans cible compte les validations.

Une validation est refusée avec `InvalidCheckIn` quand son jour est postérieur à aujourd'hui, antérieur au premier jour
de l'habitude, plus éloigné dans le passé que le nombre de jours que `Habits` permet — sept par défaut —, ou quand
l'habitude est archivée.

`checkIn()` renvoie le `Progress` de l'habitude et répartit ses [événements](events.md).

## Revenir en arrière, geler, annuler

- **Revenir en arrière.** Un membre qui a oublié hier le valide avec `day: $yesterday`, dans la limite des jours permis.
- **Geler.** `freeze($id, $from, $through)` gèle une suite de jours, jusqu'à un an d'avance et sur 366 jours au plus ;
  les jours déjà faits, en entier ou en partie, restent tels quels. Elle renvoie le nombre de jours gelés.
- **Annuler.** `undo($id, $day)` oublie ce qui était enregistré un jour, y compris un jour gelé d'avance.

## Les séries

`Progress::$streak` contient la série courante, la meilleure et l'unité dans laquelle elles comptent — jours, fenêtres,
semaines ou mois, selon l'horaire. Chaque période écoulée est l'une de trois choses :

| Une période | Quand | La série |
|---|---|---|
| Respectée | Ses validations apportent les unités qu'elle demande. | Augmente de un. |
| Excusée | Elle est incomplète, mais ses jours sautés et gelés, comptés comme faits, l'auraient respectée. | Reste. |
| Manquée | Tout le reste. | Retombe à zéro ; la meilleure reste. |

La période qui contient aujourd'hui est respectée dès qu'elle l'est, et sinon attend sans rien briser : un membre qui n'a
pas encore lu aujourd'hui garde sa série jusqu'à la fin de la journée.

Un jour fait en partie mais en deçà de la cible ne respecte pas une période quotidienne ; il compte tout de même dans
la force, qui est le nombre conçu pour récompenser l'effort en deçà du but.

## Où en est une habitude

`Habits::progress($id)` donne le `Progress` d'une habitude aujourd'hui : ses séries, sa force de 0 à 1 —
`strengthPercent()` pour le pourcentage entier affiché —, et `current`, le `PeriodProgress` de la période qui contient
aujourd'hui, avec les unités faites, les unités demandées et ses jours excusés, pour une ligne comme « 2 sur 3 cette
semaine ». Le progrès d'une habitude archivée s'arrête à son dernier jour.

`history($id, $from, $through)` liste les validations d'une plage, les plus anciennes d'abord, pour une vue de
calendrier.
